<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\MailboxMessage;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MailboxInboxTest extends WebTestCase
{
    public function testInboxShowsDisconnectedStateWithoutAProviderKey(): void
    {
        $client = static::createClient();
        [$entityManager, $user] = $this->createUserAndWorkspace();
        $client->loginUser($user);

        $client->request('GET', '/inbox');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Inbox');
        self::assertSelectorTextContains('#mail-setup-title', 'Connect a MailOS mailbox');
        self::assertSelectorTextContains('main', 'does not fetch bodies or attachments');
        self::assertSelectorExists('a[href="/inbox"]');
        self::assertSame(0, $entityManager->getRepository(MailboxMessage::class)->count([]));
    }

    public function testThreadHeadersCannotBeReadAcrossWorkspaces(): void
    {
        $client = static::createClient();
        [$entityManager, $user] = $this->createUserAndWorkspace();
        $foreignWorkspace = new Workspace('Foreign workspace', 'foreign-inbox');
        $entityManager->persist($foreignWorkspace);
        $foreignMessage = new MailboxMessage($foreignWorkspace, [
            'id' => 'provider-message-1',
            'threadId' => 'provider-thread-1',
            'mailboxId' => 'mailbox-1',
            'from' => 'person@example.test',
            'to' => 'owner@example.test',
            'subject' => 'Private thread',
            'receivedAt' => new \DateTimeImmutable('2026-11-14T10:00:00Z'),
            'isRead' => false,
            'threadCount' => 1,
        ]);
        $entityManager->persist($foreignMessage);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/inbox/'.$foreignMessage->getId());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('owner@example.test', $entityManager->getRepository(User::class)->find($user->getId())->getEmail());
    }

    /** @return array{EntityManagerInterface, User} */
    private function createUserAndWorkspace(): array
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE mailbox_message, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $user = new User('owner@example.test');
        $user->setPassword('not-used-in-this-test');
        $workspace = new Workspace('Owner workspace', 'owner-inbox');
        $entityManager->persist($user);
        $entityManager->persist($workspace);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->flush();

        return [$entityManager, $user];
    }
}
