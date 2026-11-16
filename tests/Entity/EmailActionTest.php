<?php
declare(strict_types=1);
namespace App\Tests\Entity;

use App\Entity\{EmailAction, BusinessMailConnection, User, Workspace};
use PHPUnit\Framework\TestCase;

final class EmailActionTest extends TestCase
{
    private function connection(): BusinessMailConnection
    {
        return new BusinessMailConnection(new Workspace('Test', 'test'), ['sender' => 'sender@example.test', 'testRecipient' => 'owner@example.test', 'imapHost' => 'imap.example.test', 'smtpHost' => 'smtp.example.test', 'smtpPort' => 465, 'username' => 'sender@example.test'], 'encrypted');
    }
    public function testApprovalMustMatchImmutablePayload(): void
    {
        $connection = $this->connection();
        $actor = new User('owner@example.test');
        $action = new EmailAction($connection, $actor, 'Test', 'Test body');
        $this->expectException(\DomainException::class);
        $action->approve($actor, str_repeat('0', 64), new \DateTimeImmutable());
    }

    public function testExpiredApprovalCannotSend(): void
    {
        $connection = $this->connection();
        $connection->setPaused(false);
        $actor = new User('owner@example.test');
        $action = new EmailAction($connection, $actor, 'Test', 'Test body');
        $action->approve($actor, $action->getPayloadHash(), new \DateTimeImmutable('-16 minutes'));
        $this->expectException(\DomainException::class);
        $action->beginAttempt($connection, new \DateTimeImmutable());
    }

    public function testConnectionEditInvalidatesApproval(): void
    {
        $connection = $this->connection();
        $actor = new User('owner@example.test');
        $action = new EmailAction($connection, $actor, 'Test', 'Test body');
        $action->approve($actor, $action->getPayloadHash(), new \DateTimeImmutable());
        $connection->configure($connection->getSettings(), 'replacement');
        $connection->setPaused(false);
        $this->expectException(\DomainException::class);
        $action->beginAttempt($connection, new \DateTimeImmutable());
    }

    public function testAcceptedActionCannotBeSentAgain(): void
    {
        $connection = $this->connection(); $connection->setPaused(false); $actor = new User('owner@example.test');
        $action = new EmailAction($connection, $actor, 'Test', 'Test body');
        $action->approve($actor, $action->getPayloadHash(), new \DateTimeImmutable());
        $action->beginAttempt($connection, new \DateTimeImmutable()); $action->finish('accepted-id');
        self::assertSame('accepted', $action->getStatus());
        self::assertSame(['owner@example.test'], $action->getPayload()['to']);
        $this->expectException(\DomainException::class);
        $action->beginAttempt($connection, new \DateTimeImmutable());
    }
    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EmailAction($this->connection(), new User('owner@example.test'), "Subject\r\nBcc: victim@example.test", 'body');
    }
}
