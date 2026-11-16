<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GmailConnection;
use App\Entity\OutreachDraft;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\SuppressedContact;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Service\GmailTokenVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OutreachSafetyTest extends WebTestCase
{
    public function testSuppressedRecipientCannotCreateProviderDraft(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE outreach_draft, suppressed_contact, gmail_connection, prospect_decision, prospect, research_run, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Outreach workspace', 'outreach-workspace');
        $user = new User('outreach-owner@example.test');
        $user->setPassword('unused-outreach-password');
        $run = new ResearchRun($workspace, 'Find a prospect for a draft test', null, ResearchRun::SOURCE_WEB_SEARCH);
        $run->complete(1);
        $prospect = new Prospect($workspace, $run, 'outreach-prospect', 'Outreach Prospect', 'Clinic', 'Islamabad', 'outreach.example');
        $prospect->decide(Prospect::STATUS_FINALIZED);
        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);
        $entityManager->persist(new GmailConnection($workspace, 'sender@example.test', static::getContainer()->get(GmailTokenVault::class)->encrypt('test-refresh-token')));
        $entityManager->persist(new SuppressedContact($workspace, $user, 'do-not-contact@example.test', 'Opt-out request'));
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/prospects/'.$prospect->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action="/outreach/prospects/'.$prospect->getId().'/draft"]')->form([
            'recipient' => 'Do-Not-Contact@example.test',
            'subject' => 'A test subject',
            'body' => 'A test body.',
            'contact_checked' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/prospects/'.$prospect->getId());
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-error', 'on the workspace suppression list');
        self::assertSame(0, $entityManager->getRepository(OutreachDraft::class)->count([]));
    }

    public function testDraftFormIsHiddenUntilLeadIsFinalized(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE outreach_draft, suppressed_contact, gmail_connection, prospect_decision, prospect, research_run, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Outreach workspace', 'outreach-workspace');
        $user = new User('outreach-owner@example.test');
        $user->setPassword('unused-outreach-password');
        $run = new ResearchRun($workspace, 'Find a prospect for review', null, ResearchRun::SOURCE_WEB_SEARCH);
        $prospect = new Prospect($workspace, $run, 'candidate-outreach', 'Candidate Outreach', 'Clinic', 'Islamabad');
        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);
        $entityManager->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/prospects/'.$prospect->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Finalize this lead in Team memory');
        self::assertCount(0, $crawler->filter('form[action="/outreach/prospects/'.$prospect->getId().'/draft"]'));
    }
}
