<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MailReplyDraft
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?MailMessage $message;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $actor;
    #[ORM\Column(length: 180)]
    private string $recipient;
    #[ORM\Column(length: 200)]
    private string $subject;
    #[ORM\Column(length: 200)]
    private string $providerDraftId;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(MailMessage $message, User $actor, string $recipient, string $subject, string $providerDraftId)
    {
        $this->workspace = $message->getWorkspace(); $this->message = $message; $this->actor = $actor;
        $this->recipient = $recipient; $this->subject = $subject; $this->providerDraftId = $providerDraftId;
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getRecipient(): string { return $this->recipient; }
    public function getSubject(): string { return $this->subject; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProviderDraftId(): string { return $this->providerDraftId; }
}
