<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AiProviderSetting;
use App\Entity\User;
use App\Entity\Workspace;
use App\Service\AiProviderConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocalOllamaConnectionTest extends WebTestCase
{
    public function testRealSymfonyProviderOperationAgainstLocalOllama(): void
    {
        if (getenv('OLLAMA_INTEGRATION') !== '1') {
            self::markTestSkipped('Set OLLAMA_INTEGRATION=1 to run a real request against a local Ollama server.');
        }

        static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $workspace = new Workspace('Ollama integration test', 'ollama-integration-test');
        $actor = new User('ollama-integration@example.test');
        $actor->setPassword('test-only-password');
        $setting = new AiProviderSetting(
            $workspace,
            $actor,
            'ollama',
            getenv('OLLAMA_TEST_MODEL') ?: 'qwen2.5:1.5b',
            getenv('OLLAMA_TEST_ENDPOINT') ?: 'http://host.docker.internal:11434/v1',
            null,
        );
        $setting->setActive(true);
        $entityManager->persist($workspace);
        $entityManager->persist($actor);
        $entityManager->persist($setting);
        $entityManager->flush();

        try {
            $latency = static::getContainer()->get(AiProviderConfiguration::class)->test($workspace, $actor, 'ollama');
            self::assertGreaterThan(0, $latency);
            self::assertNotNull($setting->getTestedAt());
        } finally {
            $entityManager->remove($setting);
            $entityManager->remove($actor);
            $entityManager->remove($workspace);
            $entityManager->flush();
        }
    }
}
