<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AiProviderSetting;

/** Fixed provider endpoints keep saved API keys from being sent to user-supplied hosts. */
final class AiProviderCatalog
{
    private const DEFINITIONS = [
        'disabled' => ['name' => 'AI off', 'endpoint' => null, 'requiresKey' => false, 'tier' => 'No model request is sent.', 'kind' => 'off', 'summary' => 'Keep AI analysis turned off for this workspace.', 'keyUrl' => null],
        'gemini' => ['name' => 'Google Gemini', 'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/', 'requiresKey' => true, 'tier' => 'Google Gemini API; check your project tier and terms.', 'kind' => 'cloud', 'summary' => 'Use Google AI Studio with a Gemini model ID.', 'keyUrl' => 'https://aistudio.google.com/app/apikey'],
        'openai' => ['name' => 'OpenAI', 'endpoint' => 'https://api.openai.com/v1', 'requiresKey' => true, 'tier' => 'OpenAI API; usage may incur charges.', 'kind' => 'cloud', 'summary' => 'Use an OpenAI API project and model ID.', 'keyUrl' => 'https://platform.openai.com/api-keys'],
        'groq' => ['name' => 'Groq', 'endpoint' => 'https://api.groq.com/openai/v1', 'requiresKey' => true, 'tier' => 'Groq API; model availability and limits vary.', 'kind' => 'cloud', 'summary' => 'Use a model available to your Groq account.', 'keyUrl' => 'https://console.groq.com/keys'],
        'openrouter' => ['name' => 'OpenRouter', 'endpoint' => 'https://openrouter.ai/api/v1', 'requiresKey' => true, 'tier' => 'OpenRouter; routing, cost and data terms depend on provider/model.', 'kind' => 'cloud', 'summary' => 'Use an OpenRouter model slug and account key.', 'keyUrl' => 'https://openrouter.ai/settings/keys'],
        'huggingface' => ['name' => 'Hugging Face', 'endpoint' => 'https://router.huggingface.co/v1', 'requiresKey' => true, 'tier' => 'Hugging Face routing; cost and data terms depend on model/provider.', 'kind' => 'cloud', 'summary' => 'Use an Inference Provider model ID and token.', 'keyUrl' => 'https://huggingface.co/settings/tokens'],
        'ollama' => ['name' => 'Ollama', 'endpoint' => null, 'requiresKey' => false, 'tier' => 'Local inference; requests stay on your configured Ollama host.', 'kind' => 'local', 'summary' => 'Run a model on your own computer with Ollama.', 'keyUrl' => 'https://ollama.com/download'],
    ];

    /** @return array<string, array{name: string, endpoint: ?string, requiresKey: bool, tier: string, kind: string, summary: string, keyUrl: ?string}> */
    public function all(): array
    {
        return self::DEFINITIONS;
    }

    /** @return array{name: string, endpoint: ?string, requiresKey: bool, tier: string, kind: string, summary: string, keyUrl: ?string} */
    public function get(string $provider): array
    {
        if (!isset(self::DEFINITIONS[$provider])) {
            throw new \InvalidArgumentException('Choose a supported AI provider.');
        }

        return self::DEFINITIONS[$provider];
    }

    public function isSafeLocalEndpoint(string $endpoint): bool
    {
        $parts = parse_url(trim($endpoint));
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'http' || !in_array(strtolower($parts['host'] ?? ''), ['host.docker.internal', 'localhost', '127.0.0.1', '::1', 'ollama'], true)) {
            return false;
        }

        return ($parts['port'] ?? null) === 11434
            && rtrim($parts['path'] ?? '', '/') === '/v1'
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }
}
