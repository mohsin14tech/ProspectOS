<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evidence_summary')]
#[ORM\Index(name: 'idx_evidence_summary_prospect_created', columns: ['prospect_id', 'created_at'])]
class EvidenceSummary
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

    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    private array $evidenceIds;

    #[ORM\Column(length: 80)]
    private string $provider;

    #[ORM\Column(length: 120)]
    private string $model;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param list<int> $evidenceIds */
    public function __construct(Workspace $workspace, Prospect $prospect, array $evidenceIds, string $provider, string $model, string $summary)
    {
        $this->workspace = $workspace;
        $this->prospect = $prospect;
        $this->evidenceIds = array_values(array_unique($evidenceIds));
        $this->provider = $provider;
        $this->model = $model;
        $this->summary = $summary;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProspect(): Prospect { return $this->prospect; }
    /** @return list<int> */
    public function getEvidenceIds(): array { return $this->evidenceIds; }
    public function getProvider(): string { return $this->provider; }
    public function getModel(): string { return $this->model; }
    public function getSummary(): string { return $this->summary; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
