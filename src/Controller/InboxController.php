<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MailboxMessage;
use App\Service\CurrentWorkspaceProvider;
use App\Service\MailOSInboxSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InboxController extends AbstractController
{
    #[Route('/inbox', name: 'app_inbox', methods: ['GET'])]
    public function index(CurrentWorkspaceProvider $workspaceProvider, MailOSInboxSync $sync, EntityManagerInterface $entityManager): Response
    {
        $workspace = $workspaceProvider->get();
        $messages = $entityManager->getRepository(MailboxMessage::class)->findBy(['workspace' => $workspace], ['receivedAt' => 'DESC'], 25);

        return $this->render('inbox/index.html.twig', [
            'workspace' => $workspace,
            'messages' => $messages,
            'mailConfigured' => $sync->isConfigured(),
        ]);
    }

    #[Route('/inbox/sync', name: 'app_inbox_sync', methods: ['POST'])]
    public function sync(Request $request, CurrentWorkspaceProvider $workspaceProvider, MailOSInboxSync $sync): Response
    {
        if (!$this->isCsrfTokenValid('mailos_inbox_sync', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh and try again.');

            return $this->redirectToRoute('app_inbox');
        }
        try {
            $count = $sync->sync($workspaceProvider->get());
            $this->addFlash('success', sprintf('Synced up to %d inbox message headers.', $count));
        } catch (\Throwable) {
            $this->addFlash('error', 'MailOS inbox sync failed. Check the read-only key, mailbox scope, and connection settings.');
        }

        return $this->redirectToRoute('app_inbox');
    }

    #[Route('/inbox/{id}', name: 'app_inbox_thread', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function thread(int $id, CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager, MailOSInboxSync $sync): Response
    {
        $workspace = $workspaceProvider->get();
        $message = $entityManager->getRepository(MailboxMessage::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$message instanceof MailboxMessage) {
            throw $this->createNotFoundException();
        }
        $thread = $entityManager->getRepository(MailboxMessage::class)->findBy([
            'workspace' => $workspace,
            'mailboxId' => $message->getMailboxId(),
            'providerThreadId' => $message->getProviderThreadId(),
        ], ['receivedAt' => 'ASC'], 25);

        return $this->render('inbox/thread.html.twig', [
            'workspace' => $workspace,
            'message' => $message,
            'thread' => $thread,
            'mailConfigured' => $sync->isConfigured(),
        ]);
    }
}
