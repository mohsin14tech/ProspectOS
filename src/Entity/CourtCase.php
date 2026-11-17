<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One Evidence Court hearing: two AI counsel argue a lead, a deterministic
 * clerk strikes uncited claims, and a human rules.
 */
#[ORM\Entity]
#[ORM\Table(name: 'court_case')]
#[ORM\Index(name: 'idx_court_case_workspace_created', columns: ['workspace_id', 'created_at'])]
class CourtCase
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ARGUED = 'argued';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RULED = 'ruled';

    public const VIA_APP = 'app';
    public const VIA_MCP = 'mcp';

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
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $openedBy;

    #[ORM\Column(length: 12)]
    private string $openedVia;

    #[ORM\Column(length: 12)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $model = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $transcript = [];

    #[ORM\Column(nullable: true)]
    private ?int $latencyMs = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $failure = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProspectDecision $ruling = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(Workspace $workspace, Prospect $prospect, ?User $openedBy, string $openedVia = self::VIA_APP)
    {
        if ($prospect->getWorkspace() !== $workspace) {
            throw new \InvalidArgumentException('A court case must stay inside the prospect workspace.');
        }
        if (!in_array($openedVia, [self::VIA_APP, self::VIA_MCP], true)) {
            throw new \InvalidArgumentException('Unsupported court channel.');
        }

        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->openedBy = $openedBy;
        $this->openedVia = $openedVia;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $transcript */
    public function recordHearing(string $provider, string $model, array $transcript, int $latencyMs): void
    {
        $this->provider = mb_substr($provider, 0, 40);
        $this->model = mb_substr($model, 0, 160);
        $this->transcript = $transcript;
        $this->latencyMs = $latencyMs;
        $this->status = self::STATUS_ARGUED;
        $this->failure = null;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Saves the conversation so far while the hearing is still running, so the page can show
     * messages as they are written. `live` says who is talking and what they are doing.
     *
     * @param array<string, mixed> $partial
     */
    public function recordProgress(array $partial, ?string $speaker, ?string $activity): void
    {
        if (self::STATUS_PENDING !== $this->status) {
            return;
        }
        $partial['live'] = ['speaker' => $speaker, 'activity' => null === $activity ? null : mb_substr($activity, 0, 120)];
        $this->transcript = $partial;
    }

    public function fail(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failure = mb_substr($reason, 0, 300);
        $this->completedAt = new \DateTimeImmutable();
    }

    public function rule(ProspectDecision $decision): void
    {
        if (self::STATUS_ARGUED !== $this->status) {
            throw new \LogicException('Only an argued case can be ruled on.');
        }
        if ($decision->getProspect() !== $this->prospect) {
            throw new \InvalidArgumentException('The ruling must concern this case.');
        }

        $this->ruling = $decision;
        $this->status = self::STATUS_RULED;
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getProspect(): Prospect { return $this->prospect; }
    public function getOpenedBy(): ?User { return $this->openedBy; }
    public function getOpenedVia(): string { return $this->openedVia; }
    public function getStatus(): string { return $this->status; }
    public function getProvider(): ?string { return $this->provider; }
    public function getModel(): ?string { return $this->model; }
    /** @return array<string, mixed> */
    public function getTranscript(): array { return $this->transcript; }
    public function getLatencyMs(): ?int { return $this->latencyMs; }
    public function getFailure(): ?string { return $this->failure; }
    public function getRuling(): ?ProspectDecision { return $this->ruling; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function isPending(): bool { return self::STATUS_PENDING === $this->status; }
}
