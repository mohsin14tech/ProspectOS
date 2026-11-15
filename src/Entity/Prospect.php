<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prospect')]
#[ORM\UniqueConstraint(name: 'uniq_prospect_workspace_slug', columns: ['workspace_id', 'slug'])]
#[ORM\Index(name: 'idx_prospect_workspace_updated', columns: ['workspace_id', 'updated_at'])]
class Prospect
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
    private ResearchRun $researchRun;

    #[ORM\Column(length: 100)]
    private string $slug;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 120)]
    private string $category;

    #[ORM\Column(length: 120)]
    private string $location;

    #[ORM\Column(length: 20)]
    private string $sourceMode = 'demo';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Workspace $workspace, ResearchRun $researchRun, string $slug, string $name, string $category, string $location)
    {
        $this->workspace = $workspace;
        $this->researchRun = $researchRun;
        $this->slug = $slug;
        $this->name = $name;
        $this->category = $category;
        $this->location = $location;
        $this->sourceMode = $researchRun->getSourceMode();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateFromRun(ResearchRun $run): void
    {
        $this->researchRun = $run;
        $this->sourceMode = $run->getSourceMode();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getResearchRun(): ResearchRun { return $this->researchRun; }
    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->name; }
    public function getCategory(): string { return $this->category; }
    public function getLocation(): string { return $this->location; }
    public function getSourceMode(): string { return $this->sourceMode; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
