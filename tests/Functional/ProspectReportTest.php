<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EvidenceItem;
use App\Entity\EvidenceSummary;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\ScoreSnapshot;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProspectReportTest extends WebTestCase
{
    public function testReportShowsStoredEvidenceAndScoreBreakdown(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        [$workspace, $prospect] = $this->createProspect($entityManager, 'owner@example.test', 'Harbor Restaurant', true);

        $client->loginUser($entityManager->getRepository(User::class)->findOneBy(['email' => 'owner@example.test']));
        $client->request('GET', '/prospects/'.$prospect->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Harbor Restaurant');
        self::assertSelectorTextContains('.evidence-state', 'Search Result');
        self::assertSelectorTextContains('.evidence-claim', 'not been verified');
        self::assertSelectorExists('a[href="https://harbor.example/restaurant"]');
        self::assertSelectorTextContains('.score-summary strong', '75');
        self::assertSelectorTextContains('.score-note', 'Unknown dimensions are excluded');
    }

    public function testProspectFromAnotherWorkspaceIsNotVisible(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        [, $foreignProspect] = $this->createProspect($entityManager, 'owner@example.test', 'Owner Prospect', false);
        $foreignWorkspace = new Workspace('Other workspace', 'other-workspace');
        $foreignRun = new ResearchRun($foreignWorkspace, 'Private prospect research', null, ResearchRun::SOURCE_DEMO);
        $privateProspect = new Prospect($foreignWorkspace, $foreignRun, 'private-prospect', 'Private Prospect', 'Sample', 'Sample');
        $entityManager->persist($foreignWorkspace);
        $entityManager->persist($foreignRun);
        $entityManager->persist($privateProspect);
        $entityManager->flush();

        $client->loginUser($entityManager->getRepository(User::class)->findOneBy(['email' => 'owner@example.test']));
        $client->request('GET', '/prospects/'.$privateProspect->getId());

        self::assertResponseStatusCodeSame(404);
        self::assertNotSame($foreignProspect->getId(), $privateProspect->getId());
    }

    public function testAnalysisIsUnavailableWithoutHuggingFaceToken(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        [, $prospect] = $this->createProspect($entityManager, 'owner@example.test', 'Harbor Restaurant', true);
        $client->loginUser($entityManager->getRepository(User::class)->findOneBy(['email' => 'owner@example.test']));

        $crawler = $client->request('GET', '/prospects/'.$prospect->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/prospects/'.$prospect->getId().'/analyze"] button:disabled');
        $form = $crawler->filter('form[action="/prospects/'.$prospect->getId().'/analyze"]')->form();
        $client->submit($form);

        self::assertResponseRedirects('/prospects/'.$prospect->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'AI is disabled');
        self::assertSame(0, $entityManager->getRepository(EvidenceSummary::class)->count([]));
    }

    /** @return array{Workspace, Prospect} */
    private function createProspect(EntityManagerInterface $entityManager, string $email, string $name, bool $withEvidence): array
    {
        $workspace = new Workspace('Owner workspace', 'owner-workspace');
        $user = new User($email);
        $user->setPassword('not-used-by-loginUser');
        $run = new ResearchRun($workspace, 'Find independent restaurants in Islamabad', null, ResearchRun::SOURCE_WEB_SEARCH);
        $run->complete(1);
        $prospect = new Prospect($workspace, $run, strtolower(str_replace(' ', '-', $name)), $name, 'Web search candidate', 'Not verified');

        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);

        if ($withEvidence) {
            $evidence = new EvidenceItem($workspace, $prospect, $run, 'web_search', 'Search candidate; business identity has not been verified.', 'Harbor Restaurant website', 'The result lists a restaurant and an online menu.');
            $evidence->markSearchResult('https://harbor.example/restaurant', 'The result lists a restaurant and an online menu.', 0.35);
            $entityManager->persist($evidence);
            $entityManager->persist(new ScoreSnapshot($workspace, $prospect, $run, 75, 75, [
                'icpFit' => ['value' => 80, 'weight' => 25, 'weightedContribution' => 20.0],
                'opportunity' => ['value' => 75, 'weight' => 30, 'weightedContribution' => 22.5],
                'evidenceQuality' => ['value' => 70, 'weight' => 20, 'weightedContribution' => 14.0],
                'contactability' => ['value' => null, 'weight' => 15, 'weightedContribution' => null],
                'intentRecency' => ['value' => null, 'weight' => 10, 'weightedContribution' => null],
            ]));
        }

        $entityManager->flush();

        return [$workspace, $prospect];
    }
}
