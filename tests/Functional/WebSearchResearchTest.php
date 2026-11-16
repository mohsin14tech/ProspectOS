<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\Workspace;
use App\Message\ProcessResearchRun;
use App\MessageHandler\ProcessResearchRunHandler;
use App\Service\DemoProspectDiscovery;
use App\Service\OpportunityScorer;
use App\Service\PublicIpResolverInterface;
use App\Service\PublicUrlPolicy;
use App\Service\SearXNGDiscoveryProvider;
use App\Service\WebsiteInspector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WebSearchResearchTest extends KernelTestCase
{
    public function testRepeatResearchReusesCanonicalProspectAndPreservesFinalizedState(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Domain dedupe test', 'domain-dedupe-test');
        $firstRun = new ResearchRun($workspace, 'Find a local business', null, ResearchRun::SOURCE_WEB_SEARCH);
        $entityManager->persist($workspace);
        $entityManager->persist($firstRun);
        $entityManager->flush();

        $makeHandler = static function (EntityManagerInterface $entityManager, string $url): ProcessResearchRunHandler {
            $provider = new SearXNGDiscoveryProvider(
                new MockHttpClient([new MockResponse(json_encode(['results' => [
                    ['title' => 'Acme Restaurant', 'url' => $url, 'content' => 'A restaurant search candidate.', 'engine' => 'Example Engine'],
                ]], JSON_THROW_ON_ERROR), ['http_code' => 200])]),
                new PublicUrlPolicy(new ResearchFixtureResolver()),
                'http://searxng:8080',
            );

            return new ProcessResearchRunHandler(
                $entityManager,
                new DemoProspectDiscovery(),
                $provider,
                new OpportunityScorer(),
                static::getContainer()->get(WebsiteInspector::class),
            );
        };

        $makeHandler($entityManager, 'https://acme.example/')->__invoke(new ProcessResearchRun($firstRun->getId()));
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy(['workspace' => $workspace]);
        self::assertInstanceOf(Prospect::class, $prospect);
        $prospect->decide(Prospect::STATUS_FINALIZED);
        $entityManager->flush();

        $secondRun = new ResearchRun($workspace, 'Find the same business again', null, ResearchRun::SOURCE_WEB_SEARCH);
        $entityManager->persist($secondRun);
        $entityManager->flush();
        $makeHandler($entityManager, 'https://www.acme.example/new-page')->__invoke(new ProcessResearchRun($secondRun->getId()));

        $entityManager->clear();
        $saved = $entityManager->getRepository(Prospect::class)->findBy(['workspace' => $workspace]);
        self::assertCount(1, $saved);
        self::assertSame('acme.example', $saved[0]->getCanonicalDomain());
        self::assertSame(Prospect::STATUS_FINALIZED, $saved[0]->getStatus());
        self::assertSame($secondRun->getId(), $saved[0]->getResearchRun()->getId());
        self::assertSame(2, $entityManager->getRepository(EvidenceItem::class)->count(['prospect' => $saved[0]]));
    }

    public function testSearchResultsPersistAsUnverifiedCandidatesWithSourceEvidence(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace RESTART IDENTITY CASCADE');

        $workspace = new Workspace('Search integration test', 'search-integration-test');
        $run = new ResearchRun($workspace, 'Find public business candidates in Islamabad', null, ResearchRun::SOURCE_WEB_SEARCH);
        $entityManager->persist($workspace);
        $entityManager->persist($run);
        $entityManager->flush();

        $publicUrlPolicy = new PublicUrlPolicy(new ResearchFixtureResolver());
        $httpClient = new MockHttpClient([new MockResponse(json_encode(['results' => [
            ['title' => 'Acme Restaurant', 'url' => 'https://acme.example/', 'content' => 'A restaurant search candidate.', 'engine' => 'Example Engine'],
            ['title' => 'Private destination', 'url' => 'http://127.0.0.1/private'],
        ]], JSON_THROW_ON_ERROR), ['http_code' => 200])]);
        $provider = new SearXNGDiscoveryProvider($httpClient, $publicUrlPolicy, 'http://searxng:8080');
        $handler = new ProcessResearchRunHandler(
            $entityManager,
            new DemoProspectDiscovery(),
            $provider,
            new OpportunityScorer(),
            static::getContainer()->get(WebsiteInspector::class),
        );

        $handler(new ProcessResearchRun($run->getId()));

        $entityManager->clear();
        $completedRun = $entityManager->find(ResearchRun::class, $run->getId());
        $prospects = $entityManager->getRepository(Prospect::class)->findBy(['researchRun' => $completedRun]);
        $evidence = $entityManager->getRepository(EvidenceItem::class)->findOneBy(['researchRun' => $completedRun]);

        self::assertSame(ResearchRun::STATUS_COMPLETED, $completedRun?->getStatus());
        self::assertSame(ResearchRun::SOURCE_WEB_SEARCH, $completedRun?->getSourceMode());
        self::assertSame(1, $completedRun?->getResultCount());
        self::assertCount(1, $prospects);
        self::assertSame('Web search candidate', $prospects[0]->getCategory());
        self::assertSame('Not verified', $prospects[0]->getLocation());
        self::assertSame('search_result', $evidence?->getVerificationState());
        self::assertSame('searxng', $evidence?->getRetrievalMethod());
        self::assertSame('https://acme.example/', $evidence?->getSourceUrl());
        self::assertStringContainsString('not been verified', $evidence?->getClaim() ?? '');
    }
}

final class ResearchFixtureResolver implements PublicIpResolverInterface
{
    public function resolve(string $host): array
    {
        return ['93.184.216.34'];
    }
}
