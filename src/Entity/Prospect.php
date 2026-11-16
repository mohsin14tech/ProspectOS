<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prospect')]
#[ORM\UniqueConstraint(name: 'uniq_prospect_workspace_slug', columns: ['workspace_id', 'slug'])]
#[ORM\Index(name: 'idx_prospect_workspace_updated', columns: ['workspace_id', 'updated_at'])]
#[ORM\UniqueConstraint(name: 'uniq_prospect_workspace_domain', columns: ['workspace_id', 'canonical_domain'])]
class Prospect
{
    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

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

    #[ORM\Column(length: 253, nullable: true)]
    private ?string $canonicalDomain = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_CANDIDATE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Workspace $workspace, ResearchRun $researchRun, string $slug, string $name, string $category, string $location, ?string $canonicalDomain = null)
    {
        $this->workspace = $workspace;
        $this->researchRun = $researchRun;
        $this->slug = $slug;
        $this->name = $name;
        $this->category = $category;
        $this->location = $location;
        $this->sourceMode = $researchRun->getSourceMode();
        $this->canonicalDomain = self::normalizeDomain($canonicalDomain);
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateFromRun(ResearchRun $run): void
    {
        $this->researchRun = $run;
        $this->sourceMode = $run->getSourceMode();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCanonicalDomain(?string $domain): void
    {
        $normalized = self::normalizeDomain($domain);
        if ($normalized !== null && $this->canonicalDomain === null) {
            $this->canonicalDomain = $normalized;
        }
    }

    public function decide(string $status): void
    {
        if (!in_array($status, [self::STATUS_NEEDS_REVIEW, self::STATUS_FINALIZED, self::STATUS_REJECTED, self::STATUS_ARCHIVED], true)) {
            throw new \InvalidArgumentException('Unsupported lead decision.');
        }

        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $domain = trim($domain);
        if (str_contains($domain, '://')) {
            $host = parse_url($domain, PHP_URL_HOST);
            $domain = is_string($host) ? $host : '';
        }
        $domain = strtolower(rtrim($domain, '.'));
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain !== '' ? $domain : null;
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getResearchRun(): ResearchRun { return $this->researchRun; }
    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->name; }
    public function getCategory(): string { return $this->category; }
    public function getLocation(): string { return $this->location; }
    public function getSourceMode(): string { return $this->sourceMode; }
    public function getCanonicalDomain(): ?string { return $this->canonicalDomain; }
    public function getStatus(): string { return $this->status; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
