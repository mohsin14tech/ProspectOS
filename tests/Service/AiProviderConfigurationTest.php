<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AiProviderConfiguration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AiProviderConfigurationTest extends KernelTestCase
{
    public function testAiStaysDisabledByDefaultWithoutProviderCredentials(): void
    {
        self::bootKernel();
        $configuration = static::getContainer()->get(AiProviderConfiguration::class);

        self::assertFalse($configuration->isConfigured());
        self::assertFalse($configuration->isEnabled());
        self::assertSame('AI disabled', $configuration->providerName());
        $this->expectException(\RuntimeException::class);
        $configuration->platform();
    }
}
