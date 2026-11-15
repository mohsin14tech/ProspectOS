<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'research_run')]
#[ORM\Index(name: 'idx_research_run_workspace_created', columns: ['workspace_id', 'created_at'])]
class ResearchRun
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const SOURCE_DEMO = 'demo';
    public const SOURCE_WEB_SEARCH = 'web_search';
    public const SOURCE_WEBSITE_INSPECTION = 'website_inspection';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\Column(type: 'text')]
    private string $objective;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(length: 48)]
    private string $stage = 'queued';

    #[ORM\Column(length: 20)]
    private string $sourceMode = 'demo';

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $websiteUrl = null;

    #[ORM\Column]
    private int $resultCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $safeError = null;

    public function __construct(Workspace $workspace, string $objective, ?string $websiteUrl = null, string $sourceMode = self::SOURCE_DEMO)
    {
        $this->workspace = $workspace;
        $this->objective = $objective;
        $this->websiteUrl = $websiteUrl;
        $this->sourceMode = $websiteUrl === null ? $sourceMode : self::SOURCE_WEBSITE_INSPECTION;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getObjective(): string { return $this->objective; }
    public function getStatus(): string { return $this->status; }
    public function getStage(): string { return $this->stage; }
    public function getSourceMode(): string { return $this->sourceMode; }
    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function getResultCount(): int { return $this->resultCount; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getSafeError(): ?string { return $this->safeError; }

    public function start(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->stage = match ($this->sourceMode) {
            self::SOURCE_WEB_SEARCH => 'discovering_web_candidates',
            self::SOURCE_WEBSITE_INSPECTION => 'inspecting_public_website',
            default => 'discovering_demo_fixtures',
        };
        $this->startedAt ??= new \DateTimeImmutable();
        $this->safeError = null;
    }

    public function setStage(string $stage): void { $this->stage = $stage; }

    public function complete(int $resultCount): void
    {
        $this->resultCount = $resultCount;
        $this->status = self::STATUS_COMPLETED;
        $this->stage = 'completed';
        $this->completedAt = new \DateTimeImmutable();
    }

    public function fail(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->stage = 'failed';
        $this->safeError = 'Research could not be completed. Retry the run or check the application logs.';
    }
}
