<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mailbox_message')]
#[ORM\UniqueConstraint(name: 'uniq_mailbox_message_external', columns: ['workspace_id', 'mailos_mailbox_id', 'provider_message_id'])]
#[ORM\Index(name: 'idx_mailbox_message_workspace_date', columns: ['workspace_id', 'received_at'])]
#[ORM\Index(name: 'idx_mailbox_message_thread', columns: ['workspace_id', 'mailos_mailbox_id', 'provider_thread_id'])]
class MailboxMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\Column(length: 160)]
    private string $providerMessageId;

    #[ORM\Column(length: 160)]
    private string $providerThreadId;

    #[ORM\Column(name: 'mailos_mailbox_id', length: 160)]
    private string $mailboxId;

    #[ORM\Column(type: 'text')]
    private string $fromAddress;

    #[ORM\Column(type: 'text')]
    private string $toAddress;

    #[ORM\Column(length: 998)]
    private string $subject;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column]
    private bool $isRead;

    #[ORM\Column]
    private int $threadCount;

    #[ORM\Column]
    private \DateTimeImmutable $syncedAt;

    /** @param array{id:string,threadId:string,mailboxId:string,from:string,to:string,subject:string,receivedAt:\DateTimeImmutable,isRead:bool,threadCount:int} $message */
    public function __construct(Workspace $workspace, array $message)
    {
        $this->workspace = $workspace;
        $this->providerMessageId = $message['id'];
        $this->providerThreadId = $message['threadId'];
        $this->mailboxId = $message['mailboxId'];
        $this->updateFromProvider($message);
    }

    /** @param array{id:string,threadId:string,mailboxId:string,from:string,to:string,subject:string,receivedAt:\DateTimeImmutable,isRead:bool,threadCount:int} $message */
    public function updateFromProvider(array $message): void
    {
        $this->fromAddress = $message['from'];
        $this->toAddress = $message['to'];
        $this->subject = $message['subject'];
        $this->receivedAt = $message['receivedAt'];
        $this->isRead = $message['isRead'];
        $this->threadCount = max(1, $message['threadCount']);
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getProviderMessageId(): string { return $this->providerMessageId; }
    public function getProviderThreadId(): string { return $this->providerThreadId; }
    public function getMailboxId(): string { return $this->mailboxId; }
    public function getFromAddress(): string { return $this->fromAddress; }
    public function getToAddress(): string { return $this->toAddress; }
    public function getSubject(): string { return $this->subject; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function isRead(): bool { return $this->isRead; }
    public function getThreadCount(): int { return $this->threadCount; }
}
