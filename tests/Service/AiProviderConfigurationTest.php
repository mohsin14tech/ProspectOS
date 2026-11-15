<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AiProviderConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

#[CoversClass(AiProviderConfiguration::class)]
final class AiProviderConfigurationTest extends TestCase
{
    public function testAiCanBeDisabledWithoutAProviderCredential(): void
    {
        $configuration = new AiProviderConfiguration(
            $this->createMock(PlatformInterface::class),
            $this->createMock(PlatformInterface::class),
            'disabled', 'qwen3.5:4b', 'http://host.docker.internal:11434/v1', '', '', 'openai/gpt-oss-120b',
        );

        self::assertFalse($configuration->isConfigured());
        self::assertFalse($configuration->isEnabled());
        self::assertSame('AI disabled', $configuration->providerName());
        $this->expectException(\RuntimeException::class);
        $configuration->platform();
    }

    public function testOllamaRequiresAHostLocalEndpoint(): void
    {
        $local = new AiProviderConfiguration(
            $this->createMock(PlatformInterface::class), $this->createMock(PlatformInterface::class),
            'ollama', 'qwen3.5:4b', 'http://host.docker.internal:11434/v1', '', '', 'unused',
        );
        $remote = new AiProviderConfiguration(
            $this->createMock(PlatformInterface::class), $this->createMock(PlatformInterface::class),
            'ollama', 'qwen3.5:4b', 'https://example.org/v1', '', '', 'unused',
        );

        self::assertTrue($local->isConfigured());
        self::assertSame('Local inference · no API charge', $local->tierLabel());
        self::assertFalse($remote->isConfigured());
    }

    public function testGeminiUsesVerifiedOfficialOriginAndDisclosesFreeTierDataUse(): void
    {
        $configuration = new AiProviderConfiguration(
            $this->createMock(PlatformInterface::class), $this->createMock(PlatformInterface::class),
            'gemini', 'gemini-2.5-flash', 'https://generativelanguage.googleapis.com/v1beta/openai/', 'test-key', '', 'unused',
        );

        self::assertTrue($configuration->isConfigured());
        self::assertSame('Gemini API · project tier must be verified in AI Studio', $configuration->tierLabel());
        self::assertStringContainsString('free-tier prompts may be used', $configuration->dataDisclosure());
    }
}
