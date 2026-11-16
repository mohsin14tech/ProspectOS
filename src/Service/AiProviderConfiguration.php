<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AiProviderSetting;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Resolves workspace-encrypted AI settings, with environment configuration as a local fallback. */
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
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretVault $vault,
        private readonly AiProviderCatalog $catalog,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function isConfigured(?Workspace $workspace = null): bool
    {
        $saved = $this->setting($workspace);
        if ($saved instanceof AiProviderSetting) {
            $definition = $this->catalog->get($saved->getProvider());
            if ('disabled' === $saved->getProvider() || trim($saved->getModel()) === '') {
                return false;
            }

            if ('ollama' === $saved->getProvider()) {
                return $this->catalog->isSafeLocalEndpoint((string) $saved->getLocalEndpoint());
            }

            if (!$definition['requiresKey'] || !$saved->hasApiKey()) {
                return false;
            }
            try {
                return '' !== $this->vault->decrypt((string) $saved->getEncryptedApiKey(), 'ai-provider:'.$saved->getProvider());
            } catch (\RuntimeException) {
                return false;
            }
        }

        return match ($this->provider) {
            'ollama' => '' !== trim($this->model) && $this->isLocalOllamaUrl(),
            'gemini' => '' !== trim($this->apiKey) && $this->isGeminiUrl() && '' !== trim($this->model),
            'openai-compatible' => '' !== trim($this->apiKey) && $this->isHttpsUrl() && '' !== trim($this->model),
            'huggingface' => '' !== trim($this->hfToken) && '' !== trim($this->hfModel),
            default => false,
        };
    }

    /** A saved workspace configuration must pass the provider test after every edit. */
    public function isReady(?Workspace $workspace = null): bool
    {
        $saved = $this->setting($workspace);
        if (null === $workspace) {
            return $this->isConfigured();
        }

        return $this->isConfigured($workspace) && $saved instanceof AiProviderSetting && null !== $saved->getTestedAt();
    }

    public function isEnabled(?Workspace $workspace = null): bool
    {
        return 'disabled' !== $this->selectedProvider($workspace);
    }

    public function selectedProvider(?Workspace $workspace = null): string
    {
        return $this->setting($workspace)?->getProvider() ?? $this->provider;
    }

    public function modelName(?Workspace $workspace = null): string
    {
        $saved = $this->setting($workspace);
        if ($saved instanceof AiProviderSetting) {
            return $saved->getModel();
        }

        return 'huggingface' === $this->provider ? $this->hfModel : $this->model;
    }

    public function providerName(?Workspace $workspace = null): string
    {
        $saved = $this->setting($workspace);
        if ($saved instanceof AiProviderSetting) {
            return $this->catalog->get($saved->getProvider())['name'];
        }

        return match ($this->provider) {
            'ollama' => 'Ollama (local)',
            'gemini' => 'Google Gemini Developer API',
            'huggingface' => 'Hugging Face Inference Providers',
            'openai-compatible' => 'OpenAI-compatible endpoint',
            default => 'AI disabled',
        };
    }

    public function tierLabel(?Workspace $workspace = null): string
    {
        $saved = $this->setting($workspace);
        if ($saved instanceof AiProviderSetting) {
            return $this->catalog->get($saved->getProvider())['tier'];
        }

        return match ($this->provider) {
            'ollama' => 'Local inference · no API charge',
            'gemini' => 'Gemini API · project tier must be verified in AI Studio',
            'huggingface', 'openai-compatible' => 'External inference · cost and limits depend on provider and account',
            default => 'AI disabled',
        };
    }

    public function dataDisclosure(?Workspace $workspace = null): string
    {
        $name = $this->providerName($workspace);
        if ('AI disabled' === $name) {
            return 'AI analysis is disabled.';
        }

        $destination = str_contains($name, '(local)') ? 'the configured local Ollama endpoint' : $name;

        return sprintf('Prospect name, research objective, source URLs, evidence claims and bounded excerpts, plus up to five similar workspace decisions, are sent to %s. Review that provider’s retention and usage terms before enabling it. Prior decisions are preference context, not evidence.', $destination);
    }

    public function platform(?Workspace $workspace = null, bool $allowUntested = false): PlatformInterface
    {
        $saved = $this->setting($workspace);
        if ($saved instanceof AiProviderSetting) {
            if (!$this->isConfigured($workspace) || (!$allowUntested && !$this->isReady($workspace))) {
                throw new \RuntimeException('Save and successfully test the selected AI provider before using it.');
            }

            $provider = $saved->getProvider();
            $definition = $this->catalog->get($provider);
            $key = null;
            if ($definition['requiresKey']) {
                $key = $this->vault->decrypt((string) $saved->getEncryptedApiKey(), 'ai-provider:'.$provider);
            }
            $endpoint = 'ollama' === $provider ? (string) $saved->getLocalEndpoint() : (string) $definition['endpoint'];

            return Factory::createPlatform(
                baseUrl: $endpoint,
                apiKey: $key,
                // Local CPU inference returns nothing until the whole answer is ready, so it needs a longer idle window.
            httpClient: $this->httpClient->withOptions('ollama' === $provider
                ? ['timeout' => 150, 'max_duration' => 180, 'max_redirects' => 0]
                : ['timeout' => 30, 'max_duration' => 60, 'max_redirects' => 0]),
                supportsEmbeddings: false,
                completionsPath: '/chat/completions',
                name: $provider,
            );
        }

        if (!$this->isConfigured($workspace)) {
            throw new \RuntimeException('AI is disabled or the selected provider is not configured.');
        }

        return 'huggingface' === $this->provider ? $this->huggingFace : $this->generic;
    }

    /**
     * Reasoning models (for example openai/gpt-oss-*) spend hidden "thinking" tokens before answering,
     * so tight answer budgets come back empty. Ask them for low effort and leave room for the answer.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function invocationOptions(string $model, array $options): array
    {
        if (1 === preg_match('#(^|/)gpt-oss-#i', $model)) {
            $options['reasoning_effort'] = 'low';
            $options['max_tokens'] = max(600, (int) ($options['max_tokens'] ?? 0) + 400);
        }

        return $options;
    }

    public function test(?Workspace $workspace = null, ?User $actor = null, ?string $provider = null): int
    {
        $saved = $provider !== null && $workspace instanceof Workspace
            ? $this->connection($workspace, $provider)
            : $this->setting($workspace);
        if ($provider !== null && !$saved instanceof AiProviderSetting) {
            throw new \RuntimeException('Save the selected provider connection before testing it.');
        }
        if ($saved instanceof AiProviderSetting ? !$this->isConnectionConfigured($saved) : !$this->isConfigured($workspace)) {
            throw new \RuntimeException('Select a provider and enter its model and API key before testing.');
        }

        $startedAt = hrtime(true);
        // A failed retest must not leave the connection appearing verified.
        if ($saved instanceof AiProviderSetting) {
            $saved->invalidateTest();
            $this->entityManager->flush();
        }
        $platform = $saved instanceof AiProviderSetting ? $this->platformForSetting($saved) : $this->platform($workspace, true);
        $model = $saved instanceof AiProviderSetting ? $saved->getModel() : $this->modelName($workspace);
        $result = $platform->invoke($model, new MessageBag(
            Message::forSystem('Follow the user request exactly. Return plain text only.'),
            Message::ofUser('Reply exactly with: ProspectOS provider test passed.'),
        ), self::invocationOptions($model, ['max_tokens' => 24, 'temperature' => 0]));
        $text = trim($result->asText());
        if ('ProspectOS provider test passed.' !== $text) {
            throw new \RuntimeException('The selected AI provider returned an invalid test response.');
        }

        $latency = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        if ($saved instanceof AiProviderSetting) {
            $saved->markTested($latency);
            $this->entityManager->flush();
        } elseif ($workspace instanceof Workspace) {
            $this->persistTestedEnvironmentProvider($workspace, $actor, $latency);
        }

        return $latency;
    }

    private function setting(?Workspace $workspace): ?AiProviderSetting
    {
        return null === $workspace ? null : $this->entityManager->getRepository(AiProviderSetting::class)->findOneBy(['workspace' => $workspace, 'active' => true]);
    }

    /** @return list<AiProviderSetting> */
    public function connections(Workspace $workspace): array
    {
        return $this->entityManager->getRepository(AiProviderSetting::class)->findBy(['workspace' => $workspace], ['provider' => 'ASC']);
    }

    public function connection(Workspace $workspace, string $provider): ?AiProviderSetting
    {
        return $this->entityManager->getRepository(AiProviderSetting::class)->findOneBy(['workspace' => $workspace, 'provider' => $provider]);
    }

    public function isConnectionConfigured(AiProviderSetting $setting): bool
    {
        $definition = $this->catalog->get($setting->getProvider());
        if ($setting->getProvider() === 'disabled' || trim($setting->getModel()) === '') {
            return false;
        }
        if ($setting->getProvider() === 'ollama') {
            return $this->catalog->isSafeLocalEndpoint((string) $setting->getLocalEndpoint());
        }
        if (!$definition['requiresKey'] || !$setting->hasApiKey()) {
            return false;
        }
        try {
            return $this->vault->decrypt((string) $setting->getEncryptedApiKey(), 'ai-provider:'.$setting->getProvider()) !== '';
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function platformForSetting(AiProviderSetting $setting): PlatformInterface
    {
        if (!$this->isConnectionConfigured($setting)) {
            throw new \RuntimeException('The selected provider connection is not configured.');
        }
        $provider = $setting->getProvider();
        $definition = $this->catalog->get($provider);
        $key = $definition['requiresKey']
            ? $this->vault->decrypt((string) $setting->getEncryptedApiKey(), 'ai-provider:'.$provider)
            : null;
        $endpoint = $provider === 'ollama' ? (string) $setting->getLocalEndpoint() : (string) $definition['endpoint'];

        return Factory::createPlatform(
            baseUrl: $endpoint,
            apiKey: $key,
            // Local CPU inference returns nothing until the whole answer is ready, so it needs a longer idle window.
            httpClient: $this->httpClient->withOptions('ollama' === $provider
                ? ['timeout' => 150, 'max_duration' => 180, 'max_redirects' => 0]
                : ['timeout' => 30, 'max_duration' => 60, 'max_redirects' => 0]),
            supportsEmbeddings: false,
            completionsPath: '/chat/completions',
            name: $provider,
        );
    }

    private function persistTestedEnvironmentProvider(Workspace $workspace, ?User $actor, int $latency): void
    {
        if (!$actor instanceof User) {
            throw new \RuntimeException('Sign in as a workspace administrator to save the tested provider connection.');
        }

        $provider = $this->provider;
        if ('openai-compatible' === $provider) {
            $configuredEndpoint = rtrim($this->baseUrl, '/');
            $provider = match ($configuredEndpoint) {
                'https://api.openai.com/v1' => 'openai',
                'https://api.groq.com/openai/v1' => 'groq',
                'https://openrouter.ai/api/v1' => 'openrouter',
                'https://router.huggingface.co/v1' => 'huggingface',
                default => '',
            };
        }

        if (!in_array($provider, AiProviderSetting::PROVIDERS, true)) {
            throw new \RuntimeException('This environment endpoint is not in the supported provider list. Configure a provider in Integrations to save a workspace-scoped connection.');
        }

        $definition = $this->catalog->get($provider);
        $model = 'huggingface' === $provider ? $this->hfModel : $this->model;
        $localEndpoint = null;
        if ('ollama' === $provider) {
            $localEndpoint = $this->baseUrl;
            if (!$this->catalog->isSafeLocalEndpoint($localEndpoint)) {
                throw new \RuntimeException('The tested Ollama endpoint is not a local port 11434 endpoint. Configure a safe local endpoint in Integrations.');
            }
        } elseif (rtrim((string) $definition['endpoint'], '/') !== rtrim($this->baseUrl, '/')) {
            throw new \RuntimeException('The environment provider endpoint differs from the fixed provider URL. Configure it in Integrations before enabling AI.');
        }

        $credential = match ($provider) {
            'huggingface' => $this->hfToken,
            'ollama', 'disabled' => '',
            default => $this->apiKey,
        };
        $encryptedKey = $definition['requiresKey'] ? $this->vault->encrypt($credential, 'ai-provider:'.$provider) : null;
        $setting = $this->connection($workspace, $provider);
        if ($setting instanceof AiProviderSetting) {
            $setting->replace($provider, $model, $localEndpoint, $encryptedKey, $actor, true);
        } else {
            $setting = new AiProviderSetting($workspace, $actor, $provider, $model, $localEndpoint, $encryptedKey);
            $setting->setActive(true);
            $this->entityManager->persist($setting);
        }
        foreach ($this->connections($workspace) as $existingConnection) {
            if ($existingConnection !== $setting) {
                $existingConnection->setActive(false);
            }
        }
        $setting->markTested($latency);
        $this->entityManager->flush();
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
