<?php
declare(strict_types=1);
namespace App\Tests\Service;

use App\Entity\{BusinessMailConnection, Workspace};
use App\Service\{PublicIpResolverInterface, PublicUrlPolicy, SecretVault};
use App\Service\Mail\{SmtpTransportFactory, PinnedImapStream, AuthenticatedSmtpTransport};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

final class MailTransportSafetyTest extends TestCase
{
    private function policy(array $ips): PublicUrlPolicy
    {
        $resolver = $this->createMock(PublicIpResolverInterface::class); $resolver->method('resolve')->willReturn($ips);
        return new PublicUrlPolicy($resolver);
    }
    public function testSmtpPinsPublicIpAndRequiresTlsWithOriginalHostname(): void
    {
        $vault = new SecretVault('test-secret');
        $settings = ['sender' => 'sender@example.test', 'testRecipient' => 'recipient@example.test', 'imapHost' => 'imap.example.test', 'smtpHost' => 'smtp.example.test', 'smtpPort' => 587, 'username' => 'sender@example.test'];
        $connection = new BusinessMailConnection(new Workspace('Test', 'test'), $settings, $vault->encrypt('test-password', 'business-mail'));
        $transport = (new SmtpTransportFactory($this->policy(['8.8.8.8']), $vault))->create($connection);
        self::assertTrue($transport->isTlsRequired());
        self::assertSame('8.8.8.8', $transport->getStream()->getHost());
        self::assertSame('smtp.example.test', $transport->getStream()->getStreamOptions()['ssl']['peer_name']);
        self::assertTrue($transport->getStream()->getStreamOptions()['ssl']['verify_peer']);
        self::assertSame('test-password', $transport->getPassword());
    }
    public function testImapRejectsMixedPublicAndPrivateDnsBeforeOpeningSocket(): void
    {
        $stream = new PinnedImapStream($this->policy(['8.8.8.8', '127.0.0.1']));
        $this->expectException(\InvalidArgumentException::class); $stream->open('ssl', 'imap.example.test', 993, 5);
    }
    public function testImapRejectsPlaintextTransport(): void
    {
        $stream = new PinnedImapStream($this->policy(['8.8.8.8']));
        $this->expectException(\RuntimeException::class); $stream->open('tcp', 'imap.example.test', 143, 5);
    }
    public function testImapRejectsOversizeLiteralWithoutAllocatingIt(): void
    {
        $stream = new PinnedImapStream($this->policy(['8.8.8.8']));
        $this->expectException(\RuntimeException::class); $stream->read(1048577);
    }
    public function testSmtpRefusesMissingStartTlsBeforeSendingPassword(): void
    {
        $stream = new ScriptedSmtpStream(["220 server ready\r\n", "250-server\r\n", "250 AUTH PLAIN\r\n"]);
        $transport = new AuthenticatedSmtpTransport('smtp.example.test', 587, false, stream: $stream);
        $transport->setRequireTls(true)->setUsername('test-user')->setPassword('test-password');
        try { $transport->start(); self::fail('Plaintext authentication must not happen.'); }
        catch (TransportException) { self::assertStringNotContainsString('AUTH PLAIN', implode('', $stream->commands)); }
    }
    public function testSmtpLoginRequiresAuthCapability(): void
    {
        $stream = new ScriptedSmtpStream(["220 server ready\r\n", "250 server\r\n", "221 bye\r\n"]);
        $transport = new AuthenticatedSmtpTransport('smtp.example.test', 465, true, stream: $stream);
        $transport->setRequireTls(true)->setUsername('test-user')->setPassword('test-password');
        $this->expectException(TransportException::class); $transport->start();
    }
    public function testSmtpAuthenticatesWithoutSendingMailDuringConnectionTest(): void
    {
        $stream = new ScriptedSmtpStream(["220 server ready\r\n", "250-server\r\n", "250 AUTH PLAIN\r\n", "235 authenticated\r\n", "221 bye\r\n"]);
        $transport = new AuthenticatedSmtpTransport('smtp.example.test', 465, true, stream: $stream);
        $transport->setRequireTls(true)->setUsername('test-user')->setPassword('test-password'); $transport->start(); $transport->stop();
        self::assertStringContainsString('AUTH PLAIN ', implode('', $stream->commands));
        self::assertStringNotContainsString('MAIL FROM', implode('', $stream->commands));
    }
}

/** Scripted wire responses. No network, real mailbox, or delivery assertion. */
final class ScriptedSmtpStream extends AbstractStream
{
    public array $commands = [];
    private bool $tls = true;
    public function __construct(private array $responses) {}
    public function setHost(string $host): void {}
    public function setPort(int $port): void {}
    public function disableTls(): void { $this->tls = false; }
    public function isTls(): bool { return $this->tls; }
    public function startTLS(): bool { $this->tls = true; return true; }
    public function initialize(): void {}
    public function write(string $bytes, bool $debug = true): void { $this->commands[] = $bytes; }
    public function readLine(): string { return array_shift($this->responses) ?? ''; }
    protected function getReadConnectionDescription(): string { return 'scripted-test'; }
}
