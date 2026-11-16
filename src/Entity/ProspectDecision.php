<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prospect_decision')]
#[ORM\Index(name: 'idx_prospect_decision_workspace_created', columns: ['workspace_id', 'created_at'])]
class ProspectDecision
{
    public const DECISION_NEEDS_REVIEW = 'needs_review';
    public const DECISION_FINALIZED = 'finalized';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_ARCHIVED = 'archived';

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

    #[ORM\Column(length: 24)]
    private string $decision;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Workspace $workspace, Prospect $prospect, User $actor, string $decision, string $reason)
    {
        if (!in_array($decision, [self::DECISION_NEEDS_REVIEW, self::DECISION_FINALIZED, self::DECISION_REJECTED, self::DECISION_ARCHIVED], true)) {
            throw new \InvalidArgumentException('Unsupported prospect decision.');
        }
        if (mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException('Decision reason must not exceed 1000 characters.');
        }

        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->actor = $actor;
        $this->decision = $decision;
        $this->reason = trim($reason);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getProspect(): Prospect { return $this->prospect; }
    public function getActor(): User { return $this->actor; }
    public function getDecision(): string { return $this->decision; }
    public function getReason(): string { return $this->reason; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
