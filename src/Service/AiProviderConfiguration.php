<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final class AiProviderConfiguration
{
    public function __construct(
        #[Autowire(service: 'ai.platform.huggingface')]
        private readonly PlatformInterface $huggingFace,
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $generic,
        #[Autowire(env: 'string:AI_PROVIDER')]
        private readonly string $provider,
        #[Autowire(env: 'string:AI_MODEL')]
        private readonly string $model,
        #[Autowire(env: 'string:AI_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire(env: 'string:AI_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'string:HF_TOKEN')]
        private readonly string $hfToken,
        #[Autowire(env: 'string:HF_MODEL')]
        private readonly string $hfModel,
    ) {
    }

    public function isConfigured(): bool
    {
        return match ($this->provider) {
            'ollama' => '' !== trim($this->model) && $this->isLocalOllamaUrl(),
            'gemini' => '' !== trim($this->apiKey) && $this->isGeminiUrl() && '' !== trim($this->model),
            'openai-compatible' => '' !== trim($this->apiKey) && $this->isHttpsUrl() && '' !== trim($this->model),
            'huggingface' => '' !== trim($this->hfToken) && '' !== trim($this->hfModel),
            default => false,
        };
    }

    public function isEnabled(): bool
    {
        return 'disabled' !== $this->provider;
    }

    public function selectedProvider(): string
    {
        return $this->provider;
    }

    public function modelName(): string
    {
        return 'huggingface' === $this->provider ? $this->hfModel : $this->model;
    }

    public function providerName(): string
    {
        return match ($this->provider) {
            'ollama' => 'Ollama (local)',
            'gemini' => 'Google Gemini Developer API',
            'huggingface' => 'Hugging Face Inference Providers',
            'openai-compatible' => 'OpenAI-compatible endpoint',
            default => 'AI disabled',
        };
    }

    public function tierLabel(): string
    {
        return match ($this->provider) {
            'ollama' => 'Local inference · no API charge',
            'gemini' => 'Gemini API · project tier must be verified in AI Studio',
            'huggingface', 'openai-compatible' => 'External inference · cost and limits depend on provider and account',
            default => 'AI disabled',
        };
    }

    public function dataDisclosure(): string
    {
        return match ($this->provider) {
            'ollama' => 'The prospect name, research objective, source URLs, claims and bounded excerpts go to the configured local Ollama endpoint. No cloud provider fallback is used.',
            'gemini' => 'The prospect name, research objective, source URLs, claims and bounded excerpts go to Google Gemini. Google says free-tier prompts may be used to improve its products; confirm this project’s tier and data terms in AI Studio before use.',
            'huggingface' => 'The prospect name, research objective, source URLs, claims and bounded excerpts go to Hugging Face Inference Providers. Model routing, limits and data terms depend on the selected model and provider.',
            'openai-compatible' => 'The prospect name, research objective, source URLs, claims and bounded excerpts go to the configured OpenAI-compatible endpoint. Verify its data terms, limits and billing before use.',
            default => 'AI analysis is disabled.',
        };
    }

    public function platform(): PlatformInterface
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('AI is disabled or the selected provider is not configured.');
        }

        return 'huggingface' === $this->provider ? $this->huggingFace : $this->generic;
    }

    public function test(): int
    {
        $startedAt = hrtime(true);
        $result = $this->platform()->invoke($this->modelName(), new MessageBag(
            Message::forSystem('Follow the user request exactly. Return plain text only.'),
            Message::ofUser('Reply exactly with: ProspectOS provider test passed.'),
        ), ['max_tokens' => 24, 'temperature' => 0]);
        $text = trim($result->asText());
        if ('ProspectOS provider test passed.' !== $text) {
            throw new \RuntimeException('The selected AI provider returned an invalid test response.');
        }

        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function isLocalOllamaUrl(): bool
    {
        $parts = parse_url($this->baseUrl);

        return is_array($parts)
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && in_array(strtolower($parts['host'] ?? ''), ['host.docker.internal', 'localhost', '127.0.0.1', '::1', 'ollama'], true);
    }

    private function isGeminiUrl(): bool
    {
        $parts = parse_url($this->baseUrl);

        return is_array($parts)
            && 'https' === ($parts['scheme'] ?? null)
            && 'generativelanguage.googleapis.com' === strtolower($parts['host'] ?? '')
            && str_starts_with($parts['path'] ?? '', '/v1beta/openai');
    }

    private function isHttpsUrl(): bool
    {
        $parts = parse_url($this->baseUrl);

        return is_array($parts) && 'https' === ($parts['scheme'] ?? null) && '' !== ($parts['host'] ?? '');
    }
}
