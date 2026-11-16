<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'outreach_draft')]
#[ORM\Index(name: 'idx_outreach_draft_workspace_created', columns: ['workspace_id', 'created_at'])]
class OutreachDraft
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Prospect $prospect;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $actor;

    #[ORM\Column(length: 180)]
    private string $recipient;

    #[ORM\Column(length: 998)]
    private string $subject;

    #[ORM\Column(length: 200)]
    private string $providerDraftId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Workspace $workspace, Prospect $prospect, User $actor, string $recipient, string $subject, string $providerDraftId)
    {
        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->actor = $actor;
        $this->recipient = mb_strtolower(trim($recipient));
        $this->subject = $subject;
        $this->providerDraftId = $providerDraftId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getProspect(): Prospect { return $this->prospect; }
    public function getActor(): User { return $this->actor; }
    public function getRecipient(): string { return $this->recipient; }
    public function getSubject(): string { return $this->subject; }
    public function getProviderDraftId(): string { return $this->providerDraftId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
