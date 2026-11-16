<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AiProviderSetting;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Service\SecretVault;
use App\Service\AiProviderConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AiProviderSettingsTest extends WebTestCase
{
    public function testOwnerCanSaveEncryptedProviderKeyAndBlankEditKeepsIt(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE ai_provider_setting, outreach_draft, suppressed_contact, gmail_connection, prospect_decision, evidence_summary, evidence_item, score_snapshot, prospect, research_run, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('AI settings workspace', 'ai-settings-workspace');
        $owner = new User('ai-owner@example.test');
        $owner->setPassword('unused-ai-password');
        $entityManager->persist($workspace);
        $entityManager->persist($owner);
        $entityManager->persist(new WorkspaceMembership($workspace, $owner, WorkspaceMembership::ROLE_OWNER));
        $entityManager->flush();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/integrations/ai');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form.ai-settings-form')->form([
            'provider' => 'gemini',
            'model' => 'gemini-example-model',
            'api_key' => 'test-secret-api-key',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/integrations/ai');
        $client->followRedirect();

        $setting = $entityManager->getRepository(AiProviderSetting::class)->findOneBy(['workspace' => $workspace]);
        self::assertInstanceOf(AiProviderSetting::class, $setting);
        self::assertTrue($setting->hasApiKey());
        self::assertNotSame('test-secret-api-key', $setting->getEncryptedApiKey());
        self::assertSame('test-secret-api-key', static::getContainer()->get(SecretVault::class)->decrypt((string) $setting->getEncryptedApiKey(), 'ai-provider:gemini'));
        self::assertNull($setting->getTestedAt());
        $configuration = static::getContainer()->get(AiProviderConfiguration::class);
        self::assertTrue($configuration->isConfigured($workspace));
        self::assertFalse($configuration->isReady($workspace));

        $crawler = $client->request('GET', '/integrations/ai');
        $edit = $crawler->filter('form.ai-settings-form')->form([
            'provider' => 'gemini',
            'model' => 'gemini-updated-model',
            'api_key' => '',
        ]);
        $client->submit($edit);
        self::assertResponseRedirects('/integrations/ai');
        $entityManager->refresh($setting);
        self::assertSame('gemini-updated-model', $setting->getModel());
        self::assertTrue($setting->hasApiKey());
        self::assertNull($setting->getTestedAt(), 'Editing a provider invalidates the previous successful test.');
        $setting->markTested(12);
        $entityManager->flush();
        self::assertTrue($configuration->isReady($workspace));
        self::assertInstanceOf(\Symfony\AI\Platform\PlatformInterface::class, $configuration->platform($workspace));

        $crawler = $client->request('GET', '/integrations/ai');
        $ollama = $crawler->filter('form.ai-settings-form')->form([
            'provider' => 'ollama',
            'model' => 'qwen2.5:1.5b',
            'local_endpoint' => 'http://host.docker.internal:11434/v1',
        ]);
        $client->submit($ollama);
        $connections = $configuration->connections($workspace);
        self::assertCount(2, $connections, 'Adding Ollama must retain the Gemini connection.');
        self::assertTrue($setting->isActive(), 'Adding a second provider must not silently change the active route.');
        self::assertSame('test-secret-api-key', static::getContainer()->get(SecretVault::class)->decrypt((string) $setting->getEncryptedApiKey(), 'ai-provider:gemini'));
        $ollamaConnection = $configuration->connection($workspace, 'ollama');
        self::assertNotNull($ollamaConnection);
        self::assertFalse($ollamaConnection->isActive());
        self::assertTrue($configuration->isConnectionConfigured($ollamaConnection));

        $crawler = $client->request('GET', '/integrations/ai');
        $csrf = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/integrations/ai', ['_token' => $csrf, 'action' => 'activate', 'provider' => 'ollama']);
        $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $freshEntityManager->clear();
        $setting = $freshEntityManager->getRepository(AiProviderSetting::class)->findOneBy(['workspace' => $workspace, 'provider' => 'gemini']);
        $ollamaConnection = $freshEntityManager->getRepository(AiProviderSetting::class)->findOneBy(['workspace' => $workspace, 'provider' => 'ollama']);
        self::assertNotNull($setting);
        self::assertNotNull($ollamaConnection);
        self::assertFalse($setting->isActive());
        self::assertTrue($ollamaConnection->isActive());
        self::assertFalse(static::getContainer()->get(AiProviderConfiguration::class)->isReady($workspace), 'Changing active routes must still require a successful test for that connection.');
    }

    public function testRegularMemberCannotOpenSharedProviderSecretSettings(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE ai_provider_setting, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Member settings workspace', 'member-settings-workspace');
        $member = new User('ai-member@example.test');
        $member->setPassword('unused-ai-password');
        $entityManager->persist($workspace);
        $entityManager->persist($member);
        $entityManager->persist(new WorkspaceMembership($workspace, $member, WorkspaceMembership::ROLE_MEMBER));
        $entityManager->flush();
        $client->loginUser($member);

        $client->request('GET', '/integrations/ai');
        self::assertResponseStatusCodeSame(403);
    }
}
