<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AiProviderCatalog;
use PHPUnit\Framework\TestCase;

final class AiProviderCatalogTest extends TestCase
{
    public function testCatalogContainsOnlyFixedCloudDestinationsAndLocalOllama(): void
    {
        $catalog = new AiProviderCatalog();

        self::assertSame('https://generativelanguage.googleapis.com/v1beta/openai/', $catalog->get('gemini')['endpoint']);
        self::assertSame('https://router.huggingface.co/v1', $catalog->get('huggingface')['endpoint']);
        self::assertTrue($catalog->isSafeLocalEndpoint('http://host.docker.internal:11434/v1'));
        self::assertTrue($catalog->isSafeLocalEndpoint('http://ollama:11434/v1'));
        self::assertFalse($catalog->isSafeLocalEndpoint('http://127.0.0.1:80/v1'));
        self::assertFalse($catalog->isSafeLocalEndpoint('http://169.254.169.254/latest/meta-data'));
        self::assertFalse($catalog->isSafeLocalEndpoint('https://attacker.example/v1'));
        self::assertFalse($catalog->isSafeLocalEndpoint('http://localhost:11434/v1?api_key=leak'));
    }
}
