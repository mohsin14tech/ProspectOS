<?php
declare(strict_types=1);
namespace App\Tests\Functional;

use App\Entity\{BusinessMailConnection, EmailAction, GmailConnection, MailMessage, MailReplyDraft, SuppressedContact, User, Workspace, WorkspaceMembership};
use App\Service\{ApprovedEmailSender, SecretVault, GmailTokenVault};
use App\Service\Mail\{BusinessMailboxInterface, EmailTransportInterface, GmailGatewayInterface};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MailWorkflowTest extends WebTestCase
{
    private function setupWorkspace(string $role = 'owner'): array
    {
        $client = static::createClient(); $client->disableReboot(); $client->catchExceptions(false);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE TABLE workspace, app_user RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Mail QA', 'mail-qa'); $user = new User('mail-'.bin2hex(random_bytes(3)).'@example.test'); $user->setPassword('unused');
        $em->persist($workspace); $em->persist($user); $em->persist(new WorkspaceMembership($workspace, $user, $role));
        $settings = ['sender' => 'sender@example.test', 'testRecipient' => 'receiver@example.test', 'imapHost' => 'imap.gmail.com', 'smtpHost' => 'smtp.gmail.com', 'smtpPort' => 465, 'username' => 'sender@example.test'];
        $connection = new BusinessMailConnection($workspace, $settings, static::getContainer()->get(SecretVault::class)->encrypt('fake-password', 'business-mail'));
        $connection->markTested(); $connection->setPaused(false); $em->persist($connection); $em->flush(); $client->loginUser($user);
        return [$client, $em, $workspace, $user, $connection];
    }
    private function row(): array { return ['id' => '100:42', 'thread' => '100:42', 'subject' => '<script>alert(1)</script>', 'sender' => 'someone@example.test', 'date' => '2026-11-15', 'messageId' => '<42@example.test>']; }

    public function testImapSyncDeduplicatesAndBodyIsEscaped(): void
    {
        [$client, $em] = $this->setupWorkspace();
        $provider = $this->createMock(BusinessMailboxInterface::class);
        $provider->expects(self::exactly(2))->method('recent')->willReturn([$this->row()]);
        $provider->expects(self::once())->method('body')->willReturn('<script>bad()</script> Plain reply');
        static::getContainer()->set(BusinessMailboxInterface::class, $provider);
        for ($i = 0; $i < 2; ++$i) {
            $page = $client->request('GET', '/inbox'); $client->submit($page->selectButton('Sync IMAP inbox')->form());
            self::assertResponseRedirects('/inbox');
        }
        self::assertSame(1, $em->getRepository(MailMessage::class)->count([]));
        $message = $em->getRepository(MailMessage::class)->findOneBy([]);
        $page = $client->request('GET', '/inbox/'.$message->getId());
        self::assertResponseHeaderSame('Cache-Control', 'max-age=0, no-store, private');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertSelectorNotExists('main script');
        $client->submit($page->selectButton('Load plain-text message')->form()); $client->followRedirect();
        self::assertSelectorTextContains('.mail-body', '<script>bad()</script>'); self::assertSelectorNotExists('main script');
    }
    public function testFailedSyncPreservesCachedMessagesAndHidesProviderSecrets(): void
    {
        [$client, $em, , , $connection] = $this->setupWorkspace();
        $em->persist(new MailMessage($connection, $this->row())); $em->flush();
        $provider = $this->createMock(BusinessMailboxInterface::class); $provider->method('recent')->willThrowException(new \RuntimeException('secret-provider-password'));
        static::getContainer()->set(BusinessMailboxInterface::class, $provider);
        $page = $client->request('GET', '/inbox'); $client->submit($page->selectButton('Sync IMAP inbox')->form()); $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Sync failed'); self::assertStringNotContainsString('secret-provider-password', $client->getResponse()->getContent());
        self::assertSelectorExists('.mail-row');
    }
    public function testGmailReplySuppressionAndDraftHistory(): void
    {
        [$client, $em, $workspace, $user] = $this->setupWorkspace();
        $gmail = new GmailConnection($workspace, 'gmail@example.test', static::getContainer()->get(GmailTokenVault::class)->encrypt('mock-refresh'));
        $gmail->setInboxAccess(true); $em->persist($gmail);
        $message = new MailMessage($gmail, ['id' => 'abc', 'thread' => 'thread', 'subject' => 'Hello', 'messageId' => '<parent@example.test>']);
        $em->persist($message); $em->persist(new SuppressedContact($workspace, $user, 'blocked@example.test', 'Opt-out')); $em->flush();
        $provider = $this->createMock(GmailGatewayInterface::class);
        $provider->expects(self::once())->method('createDraft')->with('mock-refresh', 'allowed@example.test', 'Re: Hello', 'Reply', 'thread', '<parent@example.test>')->willReturn('draft-id');
        static::getContainer()->set(GmailGatewayInterface::class, $provider);
        foreach (['blocked@example.test', 'allowed@example.test'] as $recipient) {
            $page = $client->request('GET', '/inbox/'.$message->getId());
            $client->submit($page->selectButton('Create Gmail reply draft')->form(['recipient' => $recipient, 'body' => 'Reply', 'contact_checked' => '1'])); $client->followRedirect();
            self::assertSelectorTextContains($recipient === 'blocked@example.test' ? '.flash-error' : '.flash-success', $recipient === 'blocked@example.test' ? 'suppression list' : 'Reply draft created');
        }
        self::assertSame(1, $em->getRepository(MailReplyDraft::class)->count([]));
    }
    public function testOtherWorkspaceMessageIsNotAccessibleAndMemberCannotManageConnection(): void
    {
        [$client, $em] = $this->setupWorkspace('member');
        $other = new Workspace('Other', 'other'); $gmail = new GmailConnection($other, 'other@example.test', 'encrypted');
        $message = new MailMessage($gmail, $this->row()); $em->persist($other); $em->persist($gmail); $em->persist($message); $em->flush();
        $client->catchExceptions(true);
        $client->request('GET', '/inbox/'.$message->getId()); self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/outreach/mailbox'); self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/inbox/sync/business', ['_token' => 'invalid']); self::assertResponseStatusCodeSame(403);
    }
    public function testApprovalThenSendClaimsOnceAndRecordsAcceptance(): void
    {
        [$client, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        $page = $client->request('GET', '/outreach/tests');
        $client->submit($page->selectButton('Prepare for approval')->form(['subject' => 'QA', 'body' => 'Test body', 'contact_checked' => '1']));
        $page = $client->followRedirect(); $client->submit($page->selectButton('Approve this message')->form()); $client->followRedirect();
        $action = $em->getRepository(EmailAction::class)->findOneBy([]); self::assertSame('approved', $action->getStatus());
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::once())->method('send')->willReturn('smtp-id');
        $sender = new ApprovedEmailSender($em, $transport, false);
        $sender->send($action->getId(), $workspace, $user); self::assertSame('accepted', $action->getStatus());
        $this->expectException(\DomainException::class); $sender->send($action->getId(), $workspace, $user);
    }
    public function testSuppressionAddedAfterApprovalStillBlocksSend(): void
    {
        [, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        $action = new EmailAction($connection, $user, 'QA', 'body'); $action->approve($user, $action->getPayloadHash(), new \DateTimeImmutable()); $em->persist($action);
        $em->persist(new SuppressedContact($workspace, $user, $connection->getTestRecipient(), 'Opt-out')); $em->flush();
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::never())->method('send');
        $this->expectException(\DomainException::class); (new ApprovedEmailSender($em, $transport, false))->send($action->getId(), $workspace, $user);
    }
    public function testTimeoutIsUncertainAndIsNotRetried(): void
    {
        [, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        $action = new EmailAction($connection, $user, 'QA', 'body'); $action->approve($user, $action->getPayloadHash(), new \DateTimeImmutable()); $em->persist($action); $em->flush();
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::once())->method('send')->willThrowException(new \RuntimeException('timeout'));
        $sender = new ApprovedEmailSender($em, $transport, false); $sender->send($action->getId(), $workspace, $user);
        self::assertSame('uncertain', $action->getStatus());
        $this->expectException(\DomainException::class); $sender->send($action->getId(), $workspace, $user);
    }
    public function testGlobalPauseBlocksEvenApprovedMessages(): void
    {
        [, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        $action = new EmailAction($connection, $user, 'QA', 'body'); $action->approve($user, $action->getPayloadHash(), new \DateTimeImmutable()); $em->persist($action); $em->flush();
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::never())->method('send');
        $this->expectException(\DomainException::class); (new ApprovedEmailSender($em, $transport, true))->send($action->getId(), $workspace, $user);
    }
    public function testReconnectCannotReuseOldApproval(): void
    {
        [, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        $action = new EmailAction($connection, $user, 'QA', 'body'); $action->approve($user, $action->getPayloadHash(), new \DateTimeImmutable()); $em->persist($action); $em->flush();
        $settings = $connection->getSettings(); $em->remove($connection); $em->flush(); $em->refresh($action);
        $replacement = new BusinessMailConnection($workspace, $settings, 'encrypted'); $replacement->markTested(); $replacement->setPaused(false); $em->persist($replacement); $em->flush();
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::never())->method('send');
        $this->expectException(\DomainException::class); (new ApprovedEmailSender($em, $transport, false))->send($action->getId(), $workspace, $user);
    }

    public function testEditedHostCannotReusePasswordAndErrorDoesNotRedisplaySecret(): void
    {
        [$client] = $this->setupWorkspace();
        $page = $client->request('GET', '/outreach/mailbox');
        $form = $page->selectButton('Save and test again')->form(['smtpHost' => 'other.example.test', 'password' => '']);
        $client->submit($form); self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#mailbox-error', 'Enter a new password');
        self::assertSelectorExists('input[name="password"]:not([value])');
        self::assertStringNotContainsString('fake-password', $client->getResponse()->getContent());
    }

    public function testFiveAttemptHourlyLimitBlocksSixthAction(): void
    {
        [, $em, $workspace, $user, $connection] = $this->setupWorkspace();
        for ($i = 0; $i < 5; ++$i) {
            $prior = new EmailAction($connection, $user, 'QA', 'body'); $prior->approve($user, $prior->getPayloadHash(), new \DateTimeImmutable());
            $prior->beginAttempt($connection, new \DateTimeImmutable()); $prior->finish(null); $em->persist($prior);
        }
        $action = new EmailAction($connection, $user, 'Sixth', 'body'); $action->approve($user, $action->getPayloadHash(), new \DateTimeImmutable()); $em->persist($action); $em->flush();
        $transport = $this->createMock(EmailTransportInterface::class); $transport->expects(self::never())->method('send');
        $this->expectException(\DomainException::class); $this->expectExceptionMessage('five test attempts');
        (new ApprovedEmailSender($em, $transport, false))->send($action->getId(), $workspace, $user);
    }

    public function testOneClickSetupFillsDefaultsStripsGoogleSpacesAndTestsImmediately(): void
    {
        [$client, $em, , , $connection] = $this->setupWorkspace();
        $resolver = $this->createStub(\App\Service\PublicIpResolverInterface::class);
        $resolver->method('resolve')->willReturn(['142.250.4.108']);
        static::getContainer()->set(\App\Service\PublicIpResolverInterface::class, $resolver);
        $vault = static::getContainer()->get(SecretVault::class);
        $seen = null;
        $provider = $this->createMock(BusinessMailboxInterface::class);
        $provider->expects(self::once())->method('test')->willReturnCallback(static function (BusinessMailConnection $saved) use (&$seen, $vault): void {
            $seen = ['password' => $vault->decrypt($saved->getEncryptedPassword(), 'business-mail'), 'settings' => $saved->getSettings()];
        });
        static::getContainer()->set(BusinessMailboxInterface::class, $provider);

        $page = $client->request('GET', '/outreach/mailbox');
        $client->submit($page->selectButton('Save and test again')->form([
            'sender' => 'owner@gmail.com', 'username' => '', 'testRecipient' => '', 'password' => 'abcd efgh ijkl mnop',
            'imapHost' => 'IMAP.gmail.com', 'smtpHost' => 'smtp.gmail.com', 'smtpPort' => '465',
        ]));
        self::assertResponseRedirects('/outreach/mailbox');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Mailbox connected');
        self::assertSame('abcdefghijklmnop', $seen['password']);
        self::assertSame('owner@gmail.com', $seen['settings']['username']);
        self::assertSame('owner@gmail.com', $seen['settings']['testRecipient']);
        self::assertSame('imap.gmail.com', $seen['settings']['imapHost']);
        $em->clear();
        self::assertNotNull($em->getRepository(BusinessMailConnection::class)->find($connection->getId())->getTestedAt());
    }

    public function testRejectedGoogleLoginExplainsAppPasswordsWithoutLeakingServerText(): void
    {
        [$client] = $this->setupWorkspace();
        $provider = $this->createMock(BusinessMailboxInterface::class);
        $provider->method('test')->willThrowException(new \RuntimeException('NO [AUTHENTICATIONFAILED] Invalid credentials secret-server-detail'));
        static::getContainer()->set(BusinessMailboxInterface::class, $provider);
        $page = $client->request('GET', '/outreach/mailbox');
        $client->submit($page->selectButton('Test IMAP and SMTP')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Google rejected the login');
        self::assertStringNotContainsString('secret-server-detail', $client->getResponse()->getContent());
    }
}
