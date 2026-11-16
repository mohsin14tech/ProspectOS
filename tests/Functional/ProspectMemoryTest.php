<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\ResearchRun;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProspectMemoryTest extends WebTestCase
{
    public function testMemberCanFinalizeLeadAndSeeItInWorkspaceMemory(): void
    {
        $client = static::createClient();
        [$entityManager, $user, $workspace, $prospect] = $this->makeProspect();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/prospects/'.$prospect->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/prospects/'.$prospect->getId().'/decision"]')->form([
            'decision' => ProspectDecision::DECISION_FINALIZED,
            'reason' => 'The source supports our target customer profile.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/prospects/'.$prospect->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Decision saved in your workspace memory');
        self::assertSame(Prospect::STATUS_FINALIZED, $entityManager->getRepository(Prospect::class)->find($prospect->getId())->getStatus());
        self::assertSame(1, $entityManager->getRepository(ProspectDecision::class)->count(['workspace' => $workspace, 'prospect' => $prospect]));

        $client->request('GET', '/memory');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Prospect memory');
        self::assertSelectorTextContains('main', 'The source supports our target customer profile.');
        self::assertSelectorTextContains('main', 'Finalized leads');
    }

    public function testDecisionCannotBeMadeForAnotherWorkspaceLead(): void
    {
        $client = static::createClient();
        [$entityManager, $user] = $this->makeProspect();
        $workspace = new Workspace('Private workspace', 'private-memory');
        $run = new ResearchRun($workspace, 'Private objective', null, ResearchRun::SOURCE_DEMO);
        $foreign = new Prospect($workspace, $run, 'private-memory-prospect', 'Private Prospect', 'Sample', 'Sample');
        $entityManager->persist($workspace);
        $entityManager->persist($run);
        $entityManager->persist($foreign);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('POST', '/prospects/'.$foreign->getId().'/decision', [
            '_token' => 'irrelevant',
            'decision' => ProspectDecision::DECISION_FINALIZED,
            'reason' => 'Must not be recorded.',
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $entityManager->getRepository(ProspectDecision::class)->count(['prospect' => $foreign]));
    }

    public function testCanonicalDomainIsNormalized(): void
    {
        self::assertSame('example.com', Prospect::normalizeDomain(' HTTPS://WWW.EXAMPLE.COM/path '));
        self::assertSame('example.com', Prospect::normalizeDomain('www.Example.com.'));
        self::assertNull(Prospect::normalizeDomain(null));
    }

    /** @return array{EntityManagerInterface, User, Workspace, Prospect} */
    private function makeProspect(): array
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE prospect_decision, prospect, research_run, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Memory workspace', 'memory-workspace');
        $user = new User('memory-owner@example.test');
        $user->setPassword('unused-memory-password');
        $run = new ResearchRun($workspace, 'Find a useful prospect for our service', null, ResearchRun::SOURCE_DEMO);
        $run->complete(1);
        $prospect = new Prospect($workspace, $run, 'memory-prospect', 'Memory Prospect', 'Restaurant', 'Islamabad', 'www.memory.example.');
        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);
        $entityManager->flush();

        return [$entityManager, $user, $workspace, $prospect];
    }
}
