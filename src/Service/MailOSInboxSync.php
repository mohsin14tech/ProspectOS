<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MailboxMessage;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class MailOSInboxSync
{
    public function __construct(
        private readonly MailOSInboxProvider $provider,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    public function sync(Workspace $workspace): int
    {
        $messages = $this->provider->fetchInbox(25);
        foreach ($messages as $message) {
            $saved = $this->entityManager->getRepository(MailboxMessage::class)->findOneBy([
                'workspace' => $workspace,
                'mailboxId' => $message['mailboxId'],
                'providerMessageId' => $message['id'],
            ]);
            if ($saved instanceof MailboxMessage) {
                $saved->updateFromProvider($message);
                continue;
            }
            $this->entityManager->persist(new MailboxMessage($workspace, $message));
        }
        $this->entityManager->flush();

        return count($messages);
    }
}
