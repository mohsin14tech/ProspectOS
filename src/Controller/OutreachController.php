<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GmailConnection;
use App\Entity\OutreachDraft;
use App\Entity\Prospect;
use App\Entity\SuppressedContact;
use App\Entity\User;
use App\Service\CurrentWorkspaceProvider;
use App\Service\Mail\GmailGatewayInterface;
use App\Service\GmailTokenVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OutreachController extends AbstractController
{
    #[Route('/outreach', name: 'app_outreach', methods: ['GET'])]
    public function index(CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager, GmailGatewayInterface $gmail): Response
    {
        $workspace = $workspaceProvider->get();

        return $this->render('outreach/index.html.twig', [
            'workspace' => $workspace,
            'gmailConfigured' => $gmail->isConfigured(),
            'connection' => $entityManager->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspace]),
            'business' => $entityManager->getRepository(\App\Entity\BusinessMailConnection::class)->findOneBy(['workspace' => $workspace]),
            'drafts' => $entityManager->getRepository(OutreachDraft::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC'], 20),
            'suppressedContacts' => $entityManager->getRepository(SuppressedContact::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC'], 100),
        ]);
    }

    #[Route('/outreach/suppression', name: 'app_outreach_suppress', methods: ['POST'])]
    public function suppress(Request $request, CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('suppress_contact', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the outreach page and try again.');

            return $this->redirectToRoute('app_outreach');
        }
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $reason = trim((string) $request->request->get('reason', ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $reason === '' || mb_strlen($reason) > 500) {
            $this->addFlash('error', 'Enter a valid email address and a suppression reason up to 500 characters.');

            return $this->redirectToRoute('app_outreach');
        }
        $workspace = $workspaceProvider->get();
        $existing = $entityManager->getRepository(SuppressedContact::class)->findOneBy(['workspace' => $workspace, 'emailAddress' => $email]);
        if ($existing instanceof SuppressedContact) {
            $this->addFlash('error', 'That email address is already on this workspace suppression list.');

            return $this->redirectToRoute('app_outreach');
        }

        $entityManager->persist(new SuppressedContact($workspace, $actor, $email, $reason));
        $entityManager->flush();
        $this->addFlash('success', 'Address added to the workspace suppression list. ProspectOS will block drafts to it.');

        return $this->redirectToRoute('app_outreach');
    }

    #[Route('/outreach/gmail/connect', name: 'app_outreach_gmail_connect', methods: ['POST'])]
    public function connect(Request $request, GmailGatewayInterface $gmail, CurrentWorkspaceProvider $workspaceProvider): Response
    {
        $workspace = $workspaceProvider->requireAdmin();
        if (!$this->isCsrfTokenValid('connect_gmail', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the outreach page and try again.');

            return $this->redirectToRoute('app_outreach');
        }
        if (!$gmail->isConfigured()) {
            $this->addFlash('error', 'Gmail OAuth is not configured for this environment.');

            return $this->redirectToRoute('app_outreach');
        }

        $state = bin2hex(random_bytes(32));
        $request->getSession()->set('gmail_outreach_state', ['value' => $state, 'workspace' => $workspace->getId(), 'created' => time()]);
        try {
            return $this->redirect($gmail->authorizationUrl($state));
        } catch (\Throwable) {
            $request->getSession()->remove('gmail_outreach_state');
            $this->addFlash('error', 'Gmail connection could not be started. Check the OAuth client and callback URI.');

            return $this->redirectToRoute('app_outreach');
        }
    }

    #[Route('/outreach/gmail/callback', name: 'app_outreach_gmail_callback', methods: ['GET'])]
    public function callback(
        Request $request,
        CurrentWorkspaceProvider $workspaceProvider,
        EntityManagerInterface $entityManager,
        GmailGatewayInterface $gmail,
        GmailTokenVault $vault,
    ): Response {
        $workspace = $workspaceProvider->requireAdmin();
        $session = $request->getSession();
        $pending = $session->get('gmail_outreach_state');
        $expected = is_array($pending) ? ($pending['value'] ?? '') : '';
        $received = (string) $request->query->get('state', '');
        $session->remove('gmail_outreach_state');
        if ($expected === '' || !hash_equals($expected, $received) || ($pending['workspace'] ?? null) !== $workspace->getId() || ($pending['created'] ?? 0) < time() - 600) {
            $this->addFlash('error', 'Gmail connection could not be verified. Start again from Outreach.');

            return $this->redirectToRoute('app_outreach');
        }
        if ($request->query->get('error') || !is_string($request->query->get('code'))) {
            $this->addFlash('error', 'Gmail connection was cancelled or denied. No mailbox was connected.');

            return $this->redirectToRoute('app_outreach');
        }

        try {
            $credentials = $gmail->exchangeCode((string) $request->query->get('code'));
            $workspace = $workspaceProvider->get();
            $connection = $entityManager->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspace]);
            if ($connection instanceof GmailConnection) {
                $entityManager->createQuery('DELETE FROM App\Entity\MailMessage m WHERE m.gmailConnection = :connection')->setParameter('connection', $connection)->execute();
                $connection->replaceCredentials($credentials['email'], $vault->encrypt($credentials['refreshToken']));
            } else {
                $connection = new GmailConnection($workspace, $credentials['email'], $vault->encrypt($credentials['refreshToken']));
                $entityManager->persist($connection);
            }
            $connection->setInboxAccess($credentials['inboxAccess']);
            $entityManager->flush();
            $this->addFlash('success', 'Gmail connected for inbox reads and draft creation. Gmail messages are sent manually from Gmail.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Gmail could not be connected. Check OAuth setup, Gmail API access and the requested scope.');
        }

        return $this->redirectToRoute('app_outreach');
    }

    #[Route('/outreach/gmail/disconnect', name: 'app_outreach_gmail_disconnect', methods: ['POST'])]
    public function disconnect(Request $request, CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager): Response
    {
        $workspaceProvider->requireAdmin();
        if (!$this->isCsrfTokenValid('disconnect_gmail', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the outreach page and try again.');

            return $this->redirectToRoute('app_outreach');
        }

        $connection = $entityManager->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspaceProvider->get()]);
        if ($connection instanceof GmailConnection) {
            $entityManager->remove($connection);
            $entityManager->flush();
            $this->addFlash('success', 'Gmail was disconnected from ProspectOS. Revoke the app grant in your Google Account if you also want to remove its authorization.');
        }

        return $this->redirectToRoute('app_outreach');
    }

    #[Route('/outreach/prospects/{id}/draft', name: 'app_outreach_create_draft', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function createDraft(
        int $id,
        Request $request,
        CurrentWorkspaceProvider $workspaceProvider,
        EntityManagerInterface $entityManager,
        GmailGatewayInterface $gmail,
        GmailTokenVault $vault,
        \App\Service\Mail\MailOperationLimiter $limiter,
    ): Response {
        $workspace = $workspaceProvider->get();
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$prospect instanceof Prospect) {
            throw $this->createNotFoundException('Prospect not found.');
        }
        if (!$this->isCsrfTokenValid('create_outreach_draft_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the prospect report and try again.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }
        if ($prospect->getStatus() !== Prospect::STATUS_FINALIZED) {
            $this->addFlash('error', 'Finalize this lead in Team memory before preparing outreach.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }
        if ((string) $request->request->get('contact_checked', '') !== '1') {
            $this->addFlash('error', 'Confirm that you checked the recipient and have a valid basis to contact them.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $connection = $entityManager->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspace]);
        if (!$connection instanceof GmailConnection) {
            $this->addFlash('error', 'Connect a Gmail account before creating a provider draft.');

            return $this->redirectToRoute('app_outreach');
        }

        $recipient = trim((string) $request->request->get('recipient', ''));
        $subject = trim((string) $request->request->get('subject', ''));
        $body = trim((string) $request->request->get('body', ''));
        $limiter->consume($actor->getUserIdentifier());
        try {
            if ($entityManager->getRepository(SuppressedContact::class)->findOneBy(['workspace' => $workspace, 'emailAddress' => mb_strtolower($recipient)]) instanceof SuppressedContact) {
                throw new \DomainException('This recipient is on the workspace suppression list. No Gmail draft was created.');
            }
            $providerDraftId = $gmail->createDraft($vault->decrypt($connection->getEncryptedRefreshToken()), $recipient, $subject, $body);
            $entityManager->persist(new OutreachDraft($workspace, $prospect, $actor, $recipient, $subject, $providerDraftId));
            $entityManager->flush();
            $this->addFlash('success', 'Draft created in Gmail. Nothing was sent. Review it in Gmail before deciding whether to send.');
        } catch (\Throwable $exception) {
            $message = match (true) {
                $exception instanceof \InvalidArgumentException, $exception instanceof \DomainException => $exception->getMessage(),
                default => 'Gmail could not create the draft. Check the connection and try again.',
            };
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
    }
}
