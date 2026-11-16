<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_provider_setting')]
#[ORM\UniqueConstraint(name: 'uniq_ai_provider_workspace_provider', columns: ['workspace_id', 'provider'])]
class AiProviderSetting
{
    public const PROVIDERS = ['disabled', 'gemini', 'openai', 'groq', 'openrouter', 'huggingface', 'ollama'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $updatedBy;

    #[ORM\Column(length: 32)]
    private string $provider;

    #[ORM\Column(length: 160)]
    private string $model;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $localEndpoint;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedApiKey;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $testedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastTestLatencyMs = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $active = false;

    public function __construct(Workspace $workspace, User $updatedBy, string $provider, string $model, ?string $localEndpoint, ?string $encryptedApiKey)
    {
        $this->workspace = $workspace;
        $this->updatedBy = $updatedBy;
        $this->replace($provider, $model, $localEndpoint, $encryptedApiKey);
    }

    public function replace(string $provider, string $model, ?string $localEndpoint, ?string $encryptedApiKey, ?User $updatedBy = null, bool $active = false): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException('Choose a supported AI provider.');
        }
        if (trim($model) === '' || mb_strlen($model) > 160) {
            throw new \InvalidArgumentException('Enter a model name up to 160 characters.');
        }

        $this->provider = $provider;
        $this->model = trim($model);
        if ($updatedBy instanceof User) {
            $this->updatedBy = $updatedBy;
        }
        $this->localEndpoint = 'ollama' === $provider ? trim((string) $localEndpoint) : null;
        $this->encryptedApiKey = in_array($provider, ['ollama', 'disabled'], true) ? null : $encryptedApiKey;
        $this->updatedAt = new \DateTimeImmutable();
        $this->testedAt = null;
        $this->lastTestLatencyMs = null;
        $this->active = $active;
    }

    public function markTested(int $latencyMs): void
    {
        $this->testedAt = new \DateTimeImmutable();
        $this->lastTestLatencyMs = max(0, $latencyMs);
    }

    public function setActive(bool $active): void { $this->active = $active; }

    public function invalidateTest(): void
    {
        $this->testedAt = null;
        $this->lastTestLatencyMs = null;
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getUpdatedBy(): User { return $this->updatedBy; }
    public function getProvider(): string { return $this->provider; }
    public function getModel(): string { return $this->model; }
    public function getLocalEndpoint(): ?string { return $this->localEndpoint; }
    public function getEncryptedApiKey(): ?string { return $this->encryptedApiKey; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getTestedAt(): ?\DateTimeImmutable { return $this->testedAt; }
    public function getLastTestLatencyMs(): ?int { return $this->lastTestLatencyMs; }
    public function isActive(): bool { return $this->active; }
    public function hasApiKey(): bool { return $this->encryptedApiKey !== null; }
}
