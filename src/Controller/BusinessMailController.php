<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{BusinessMailConnection, EmailAction, MailMessage, User};
use App\Service\{ApprovedEmailSender, CurrentWorkspaceProvider, PublicUrlPolicy, SecretVault};
use App\Service\Mail\BusinessMailboxInterface;
use App\Service\Mail\MailOperationLimiter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class BusinessMailController extends AbstractController
{
    public function __construct(private readonly CurrentWorkspaceProvider $workspaces, private readonly EntityManagerInterface $em,
        #[Autowire(env: 'bool:OUTBOUND_EMAIL_PAUSED')] private readonly bool $globallyPaused = true) {}

    #[Route('/outreach/mailbox', name: 'app_mailbox', methods: ['GET'])]
    public function index(): Response
    {
        $workspace = $this->workspaces->requireAdmin();
        return $this->render('outreach/mailbox.html.twig', ['workspace' => $workspace, 'connection' => $this->connection(),
            'globallyPaused' => $this->globallyPaused, 'error' => null, 'values' => null]);
    }

    #[Route('/outreach/mailbox/save', name: 'app_mailbox_save', methods: ['POST'])]
    public function save(Request $request, SecretVault $vault, PublicUrlPolicy $policy, BusinessMailboxInterface $mailbox, MailOperationLimiter $limiter): Response
    {
        $workspace = $this->workspaces->requireAdmin();
        $this->csrf($request, 'mailbox_save');
        $values = [];
        foreach (['sender', 'testRecipient', 'imapHost', 'smtpHost', 'username'] as $key) { $values[$key] = trim($request->request->getString($key)); }
        $values['imapHost'] = strtolower($values['imapHost']);
        $values['smtpHost'] = strtolower($values['smtpHost']);
        // Most providers log in with the full address, and the safest test recipient is your own mailbox.
        if ($values['username'] === '') { $values['username'] = $values['sender']; }
        if ($values['testRecipient'] === '') { $values['testRecipient'] = $values['sender']; }
        $values['smtpPort'] = $request->request->getInt('smtpPort');
        $password = self::normalizePassword($request->request->getString('password'), $values['imapHost']);
        $connection = $this->connection();
        try {
            if (strlen($password) > 1024 || str_contains($password, "\0")) { throw new \InvalidArgumentException('Use a mailbox password or app password up to 1024 bytes.'); }
            // Never forward an old secret to an edited host/account without new credentials.
            if ($password === '' && (!$connection || array_intersect_key($values, array_flip(['imapHost', 'smtpHost', 'username'])) !== array_intersect_key($connection->getSettings(), array_flip(['imapHost', 'smtpHost', 'username'])))) {
                throw new \InvalidArgumentException('Enter a new password when changing servers or mailbox username.');
            }
            $encrypted = $password !== '' ? $vault->encrypt($password, 'business-mail') : $connection->getEncryptedPassword();
            $validated = new BusinessMailConnection($workspace, $values, $encrypted);
            $policy->validateAndPin('https://'.$values['imapHost'].'/');
            $policy->validateAndPin('https://'.$values['smtpHost'].'/');
            $this->em->wrapInTransaction(function () use ($connection, $validated, $values, $encrypted): void {
                if ($connection) {
                    $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
                    $connection->configure($values, $encrypted);
                    $this->em->createQuery('DELETE FROM App\Entity\MailMessage m WHERE m.businessConnection = :connection')->setParameter('connection', $connection)->execute();
                } else { $this->em->persist($validated); }
            });
            $limiter->consume($this->getUser()->getUserIdentifier());
            $failure = $this->verify($connection ?? $validated, $mailbox);
            if ($failure === null) {
                $this->addFlash('success', 'Mailbox connected. IMAP and SMTP login both passed, and no email was sent. Open the inbox to sync recent messages.');
            } else {
                $this->addFlash('error', 'Mailbox saved, but the login test failed. '.$failure);
            }
            return $this->redirectToRoute('app_mailbox');
        } catch (\InvalidArgumentException $e) {
            return $this->render('outreach/mailbox.html.twig', ['workspace' => $workspace, 'connection' => $connection,
                'globallyPaused' => $this->globallyPaused, 'error' => $e->getMessage(), 'values' => $values], new Response(status: 422));
        }
    }

    #[Route('/outreach/mailbox/test', name: 'app_mailbox_test', methods: ['POST'])]
    public function test(Request $request, BusinessMailboxInterface $mailbox, MailOperationLimiter $limiter): Response
    {
        $this->workspaces->requireAdmin(); $this->csrf($request, 'mailbox_test');
        $limiter->consume($this->getUser()->getUserIdentifier());
        $failure = $this->verify($this->requiredConnection(), $mailbox);
        if ($failure === null) { $this->addFlash('success', 'IMAP and SMTP login passed. No email was sent. You can now sync the inbox.'); }
        else { $this->addFlash('error', 'Mailbox test failed. '.$failure); }
        return $this->redirectToRoute('app_mailbox');
    }

    #[Route('/outreach/mailbox/pause', name: 'app_mailbox_pause', methods: ['POST'])]
    public function pause(Request $request): Response
    {
        $this->workspaces->requireAdmin(); $this->csrf($request, 'mailbox_pause');
        $connection = $this->requiredConnection();
        $paused = $request->request->getString('paused') !== '0';
        $this->em->wrapInTransaction(function () use ($connection, $paused): void {
            $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
            if (!$paused && ($connection->getTestedAt() === null || $this->globallyPaused)) { throw $this->createAccessDeniedException('Test the connection and ask the server operator to enable test sending first.'); }
            $connection->setPaused($paused);
        });
        $this->addFlash('success', $paused ? 'Workspace test sending paused. Already claimed attempts may finish.' : 'Approved tests may now be sent to the configured test recipient.');
        return $this->redirectToRoute('app_mailbox');
    }

    #[Route('/outreach/mailbox/disconnect', name: 'app_mailbox_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): Response
    {
        $this->workspaces->requireAdmin(); $this->csrf($request, 'mailbox_disconnect');
        $connection = $this->requiredConnection();
        $this->em->wrapInTransaction(function () use ($connection): void {
            $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
            $this->em->remove($connection);
        });
        $this->addFlash('success', 'Mailbox credentials and cached messages removed from ProspectOS. Test history is retained.');
        return $this->redirectToRoute('app_outreach');
    }

    #[Route('/outreach/tests', name: 'app_email_tests', methods: ['GET'])]
    public function tests(): Response
    {
        $workspace = $this->workspaces->requireAdmin();
        return $this->render('outreach/tests.html.twig', ['workspace' => $workspace, 'connection' => $this->connection(), 'globallyPaused' => $this->globallyPaused,
            'actions' => $this->em->getRepository(EmailAction::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC'], 30)]);
    }

    #[Route('/outreach/tests/create', name: 'app_email_test_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->workspaces->requireAdmin(); $this->csrf($request, 'email_test_create');
        if ($request->request->getString('contact_checked') !== '1') { throw $this->createAccessDeniedException('Confirm you control the test address and reviewed the mailbox provider rules.'); }
        try {
            $action = new EmailAction($this->requiredConnection(), $this->actor(), trim($request->request->getString('subject')), trim($request->request->getString('body')));
            $this->em->persist($action); $this->em->flush();
            $this->addFlash('success', 'Test prepared. Review the exact message below and approve it separately.');
        } catch (\InvalidArgumentException $e) { $this->addFlash('error', $e->getMessage()); }
        return $this->redirectToRoute('app_email_tests');
    }

    #[Route('/outreach/tests/{id}/{decision}', name: 'app_email_test_decide', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function decide(int $id, string $decision, Request $request): Response
    {
        $workspace = $this->workspaces->requireAdmin(); $this->csrf($request, 'email_'.$decision.'_'.$id);
        $action = $this->em->getRepository(EmailAction::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$action) { throw $this->createNotFoundException(); }
        try {
            $this->em->wrapInTransaction(function () use ($action, $decision, $request): void {
                $this->em->refresh($action, LockMode::PESSIMISTIC_WRITE);
                if ($decision === 'approve') { $action->approve($this->actor(), $request->request->getString('payload_hash'), new \DateTimeImmutable()); }
                else { $action->reject(); }
            });
            $this->addFlash('success', $decision === 'approve' ? 'Message approved for 15 minutes. Sending requires the separate send action.' : 'Test message rejected.');
        } catch (\DomainException $e) { $this->addFlash('error', $e->getMessage()); }
        return $this->redirectToRoute('app_email_tests');
    }

    #[Route('/outreach/tests/{id}/send', name: 'app_email_test_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(int $id, Request $request, ApprovedEmailSender $sender): Response
    {
        $workspace = $this->workspaces->requireAdmin(); $this->csrf($request, 'email_send_'.$id);
        try { $sender->send($id, $workspace, $this->actor()); $this->addFlash('success', 'Send attempt recorded. Check its status below and verify arrival in the receiving mailbox.'); }
        catch (\DomainException $e) { $this->addFlash('error', $e->getMessage()); }
        return $this->redirectToRoute('app_email_tests');
    }

    /** Runs the real IMAP + SMTP login and returns null on success, or a safe, specific hint. */
    private function verify(BusinessMailConnection $connection, BusinessMailboxInterface $mailbox): ?string
    {
        $revision = $connection->getRevision();
        try {
            $mailbox->test($connection);
            $this->em->wrapInTransaction(function () use ($connection, $revision): void {
                $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
                if ($connection->getRevision() !== $revision) { throw new \DomainException('Connection changed.'); }
                $connection->markTested();
            });
            return null;
        } catch (\Throwable $e) {
            if ($this->em->isOpen()) { $connection->invalidateTest(); $this->em->flush(); }
            return self::explainFailure($e, (string) ($connection->getSettings()['imapHost'] ?? ''));
        }
    }

    /** Google shows app passwords as four groups of four letters; the spaces are not part of the password. */
    public static function normalizePassword(string $password, string $imapHost): string
    {
        return self::isGoogleHost($imapHost) ? (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $password) : $password;
    }

    /** Maps provider errors to an actionable hint without echoing server output or credentials. */
    public static function explainFailure(\Throwable $e, string $imapHost): string
    {
        $text = '';
        for ($current = $e; $current !== null; $current = $current->getPrevious()) { $text .= ' '.$current->getMessage(); }
        if (preg_match('/AUTHENTICATIONFAILED|invalid credentials|authentication failed|\b53[45]\b|login failed|\[AUTH\]/i', $text)) {
            return self::isGoogleHost($imapHost)
                ? 'Google rejected the login. Use your full Gmail address and a 16-letter app password from myaccount.google.com/apppasswords (2-Step Verification must be on). Your normal Google password will not work.'
                : 'The provider rejected the username or password. Many providers (Yahoo, iCloud, Zoho with 2FA) require an app-specific password.';
        }
        if (preg_match('/timed out|timeout|could not connect|connection refused|getaddrinfo|resolve|not allowed|private|blocked/i', $text)) {
            return 'Could not reach the mail server. Check the IMAP and SMTP hostnames and the SMTP port.';
        }
        return 'Check the server names, ports, username and app password. Sending remains paused.';
    }

    private static function isGoogleHost(string $host): bool
    {
        return in_array(strtolower($host), ['imap.gmail.com', 'imap.googlemail.com'], true);
    }

    private function connection(): ?BusinessMailConnection { return $this->em->getRepository(BusinessMailConnection::class)->findOneBy(['workspace' => $this->workspaces->get()]); }
    private function requiredConnection(): BusinessMailConnection { return $this->connection() ?? throw $this->createNotFoundException('Connect an IMAP/SMTP mailbox first.'); }
    private function actor(): User { $actor = $this->getUser(); return $actor instanceof User ? $actor : throw $this->createAccessDeniedException(); }
    private function csrf(Request $request, string $id): void { if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Refresh the page and try again.'); } }
}
