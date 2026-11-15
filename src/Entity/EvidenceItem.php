<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evidence_item')]
#[ORM\UniqueConstraint(name: 'uniq_evidence_run_prospect_type', columns: ['research_run_id', 'prospect_id', 'finding_type'])]
#[ORM\Index(name: 'idx_evidence_run_prospect', columns: ['research_run_id', 'prospect_id'])]
class EvidenceItem
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
    private ResearchRun $researchRun;

    #[ORM\Column(length: 80)]
    private string $findingType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: 'text')]
    private string $claim;

    #[ORM\Column(length: 200)]
    private string $sourceTitle;

    #[ORM\Column(type: 'text')]
    private string $supportingExcerpt;

    #[ORM\Column(length: 40)]
    private string $retrievalMethod = 'synthetic_fixture';

    #[ORM\Column(length: 24)]
    private string $verificationState = 'synthetic';

    #[ORM\Column(nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column]
    private \DateTimeImmutable $retrievedAt;

    public function __construct(Workspace $workspace, Prospect $prospect, ResearchRun $researchRun, string $findingType, string $claim, string $sourceTitle, string $supportingExcerpt)
    {
        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->researchRun = $researchRun;
        $this->findingType = $findingType;
        $this->claim = $claim;
        $this->sourceTitle = $sourceTitle;
        $this->supportingExcerpt = $supportingExcerpt;
        $this->retrievedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProspect(): Prospect { return $this->prospect; }
    public function getResearchRun(): ResearchRun { return $this->researchRun; }
    public function getFindingType(): string { return $this->findingType; }
    public function getClaim(): string { return $this->claim; }
    public function getSourceTitle(): string { return $this->sourceTitle; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function getSupportingExcerpt(): string { return $this->supportingExcerpt; }
    public function getRetrievalMethod(): string { return $this->retrievalMethod; }
    public function getVerificationState(): string { return $this->verificationState; }
    public function getConfidence(): ?float { return $this->confidence; }
    public function getRetrievedAt(): \DateTimeImmutable { return $this->retrievedAt; }

    public function markObservedWebsite(string $sourceUrl, string $excerpt): void
    {
        $this->sourceUrl = $sourceUrl;
        $this->supportingExcerpt = mb_substr($excerpt, 0, 500);
        $this->retrievalMethod = 'http';
        $this->verificationState = 'observed';
        $this->confidence = 0.75;
    }

    public function markSearchResult(string $sourceUrl, string $excerpt, float $confidence): void
    {
        $this->sourceUrl = $sourceUrl;
        $this->supportingExcerpt = mb_substr($excerpt, 0, 500);
        $this->retrievalMethod = 'searxng';
        $this->verificationState = 'search_result';
        $this->confidence = max(0, min(1, $confidence));
    }
}
