<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{BusinessMailConnection, GmailConnection, MailMessage, MailReplyDraft, SuppressedContact, User};
use App\Service\{CurrentWorkspaceProvider, GmailTokenVault};
use App\Service\Mail\{GmailGatewayInterface, MailboxSync};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class InboxController extends AbstractController
{
    public function __construct(private readonly CurrentWorkspaceProvider $workspaces, private readonly EntityManagerInterface $em, private readonly \App\Service\Mail\MailOperationLimiter $limiter) {}

    #[Route('/inbox', name: 'app_inbox', methods: ['GET'])]
    public function index(): Response
    {
        $workspace = $this->workspaces->get();
        return $this->render('inbox/index.html.twig', [
            'workspace' => $workspace,
            'gmail' => $this->em->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspace]),
            'business' => $this->em->getRepository(BusinessMailConnection::class)->findOneBy(['workspace' => $workspace]),
            'messages' => $this->em->getRepository(MailMessage::class)->findBy(['workspace' => $workspace], ['syncedAt' => 'DESC', 'id' => 'DESC'], 100),
        ]);
    }

    #[Route('/inbox/sync/{provider}', name: 'app_inbox_sync', requirements: ['provider' => 'gmail|business'], methods: ['POST'])]
    public function sync(string $provider, Request $request, MailboxSync $sync): Response
    {
        $workspace = $this->workspaces->get();
        $this->csrf($request, 'sync_'.$provider);
        $this->limiter->consume($this->getUser()->getUserIdentifier());
        try {
            $count = $sync->sync($workspace, $provider);
            $this->addFlash('success', sprintf('Synced %d recent messages. Your mailbox read/unread state was preserved.', $count));
        } catch (\Throwable) {
            $this->addFlash('error', 'Sync failed. Check the mailbox connection, permissions and server settings, then try again. Previously cached messages are still available.');
        }
        return $this->redirectToRoute('app_inbox');
    }

    #[Route('/inbox/clear', name: 'app_inbox_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        $workspace = $this->workspaces->requireAdmin();
        $this->csrf($request, 'clear_mail');
        $this->em->createQuery('DELETE FROM App\Entity\MailMessage m WHERE m.workspace = :workspace')->setParameter('workspace', $workspace)->execute();
        $this->addFlash('success', 'The local mailbox cache was cleared. Messages in your provider were not deleted.');
        return $this->redirectToRoute('app_inbox');
    }

    #[Route('/inbox/{id}', name: 'app_inbox_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $message = $this->message($id);
        return $this->render('inbox/show.html.twig', ['workspace' => $message->getWorkspace(), 'message' => $message,
            'drafts' => $this->em->getRepository(MailReplyDraft::class)->findBy(['workspace' => $message->getWorkspace(), 'message' => $message], ['createdAt' => 'DESC'], 10)]);
    }

    #[Route('/inbox/{id}/body', name: 'app_inbox_body', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function body(int $id, Request $request, MailboxSync $sync): Response
    {
        $message = $this->message($id);
        $this->csrf($request, 'mail_body_'.$id);
        $this->limiter->consume($this->getUser()->getUserIdentifier());
        try { $sync->fetchBody($message); $this->addFlash('success', 'Plain-text message loaded.'); }
        catch (\Throwable) { $this->addFlash('error', 'Could not load this message. It may be too large, removed, or the connection needs attention. Open it in your mail provider or try syncing again.'); }
        return $this->redirectToRoute('app_inbox_show', ['id' => $id]);
    }

    #[Route('/inbox/{id}/reply', name: 'app_inbox_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(int $id, Request $request, GmailGatewayInterface $gmail, GmailTokenVault $vault): Response
    {
        $message = $this->message($id);
        $this->csrf($request, 'mail_reply_'.$id);
        $this->limiter->consume($this->getUser()->getUserIdentifier());
        $connection = $message->getGmailConnection();
        $recipient = mb_strtolower(trim($request->request->getString('recipient')));
        $subject = trim($request->request->getString('subject'));
        $body = trim($request->request->getString('body'));
        try {
            if (!$connection || !$connection->hasInboxAccess()) { throw new \DomainException('Connect Gmail with inbox access before creating a reply draft.'); }
            if ($request->request->getString('contact_checked') !== '1') { throw new \DomainException('Check the recipient and confirm your basis for replying.'); }
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 180 || mb_strlen($subject) > 200) { throw new \DomainException('Enter a valid recipient and a subject up to 200 characters.'); }
            if ($this->em->getRepository(SuppressedContact::class)->findOneBy(['workspace' => $message->getWorkspace(), 'emailAddress' => $recipient])) { throw new \DomainException('This recipient is on the workspace suppression list.'); }
            $actor = $this->getUser();
            if (!$actor instanceof User) { throw $this->createAccessDeniedException(); }
            $providerId = $gmail->createDraft($vault->decrypt($connection->getEncryptedRefreshToken()), $recipient, $subject, $body, $message->getThreadId(), $message->getMetadata()['messageId']);
            $this->em->persist(new MailReplyDraft($message, $actor, $recipient, $subject, $providerId));
            $this->em->flush();
            $this->addFlash('success', 'Reply draft created in Gmail. Review and send it there; nothing was sent by ProspectOS.');
        } catch (\DomainException|\InvalidArgumentException $e) { $this->addFlash('error', $e->getMessage()); }
        catch (\Throwable) { $this->addFlash('error', 'Gmail could not confirm draft creation. Check Gmail Drafts before retrying to avoid a duplicate.'); }
        return $this->redirectToRoute('app_inbox_show', ['id' => $id]);
    }

    private function message(int $id): MailMessage
    {
        $message = $this->em->getRepository(MailMessage::class)->findOneBy(['id' => $id, 'workspace' => $this->workspaces->get()]);
        if (!$message) { throw $this->createNotFoundException(); }
        return $message;
    }
    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Refresh the page and try again.'); }
    }
}
