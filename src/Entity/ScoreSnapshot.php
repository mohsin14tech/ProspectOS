<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'score_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_score_run_prospect', columns: ['research_run_id', 'prospect_id'])]
class ScoreSnapshot
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

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $score;

    #[ORM\Column(type: 'smallint')]
    private int $coverage;

    /** @var array<string, array{value: ?int, weight: int, weightedContribution: ?float}> */
    #[ORM\Column(type: 'json')]
    private array $breakdown;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Workspace $workspace, Prospect $prospect, ResearchRun $researchRun, ?int $score, int $coverage, array $breakdown)
    {
        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->researchRun = $researchRun;
        $this->score = $score;
        $this->coverage = $coverage;
        $this->breakdown = $breakdown;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProspect(): Prospect { return $this->prospect; }
    public function getResearchRun(): ResearchRun { return $this->researchRun; }
    public function getScore(): ?int { return $this->score; }
    public function getCoverage(): int { return $this->coverage; }
    public function getBreakdown(): array { return $this->breakdown; }
}
