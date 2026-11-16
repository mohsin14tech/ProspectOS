<?php
declare(strict_types=1);
namespace App\Service\Mail;
use App\Entity\BusinessMailConnection;
use App\Service\{PublicUrlPolicy, SecretVault};
use DirectoryTree\ImapEngine\{Mailbox, MessageInterface};
use DirectoryTree\ImapEngine\Connection\ImapConnection;

final class BusinessMailbox implements BusinessMailboxInterface
{
    public function __construct(private readonly PublicUrlPolicy $policy, private readonly SecretVault $vault, private readonly SmtpTransportFactory $smtp) {}
    public function test(BusinessMailConnection $connection): void
    {
        $mailbox = $this->open($connection);
        try { $mailbox->inbox()->examine(); } finally { $mailbox->disconnect(); }
        $transport = $this->smtp->create($connection);
        try { $transport->start(); } finally { $transport->stop(); }
    }
    public function recent(BusinessMailConnection $connection): array
    {
        $mailbox = $this->open($connection);
        try {
            $inbox = $mailbox->inbox();
            $status = $inbox->status();
            $validity = (string) ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? '');
            if ($validity === '') { throw new \RuntimeException('Mailbox UID validity was not returned.'); }
            $messages = $inbox->messages()->since(new \DateTimeImmutable('-30 days'))->newest()->limit(10)->withHeaders()->withFlags()->withoutBody()->leaveUnread()->get();
            $rows = [];
            foreach ($messages as $message) {
                $rows[] = ['id' => $validity.':'.$message->uid(), 'thread' => $validity.':'.$message->uid(),
                    'subject' => $message->subject() ?? '(No subject)', 'sender' => $message->from()?->email() ?? '',
                    'preview' => 'Open to fetch the plain-text body.', 'date' => $message->date()?->format(DATE_ATOM) ?? '', 'messageId' => $message->messageId()];
            }
            return $rows;
        } finally { $mailbox->disconnect(); }
    }
    public function body(BusinessMailConnection $connection, string $reference): string
    {
        if (!preg_match('/^(\d+):(\d+)$/', $reference, $parts)) { throw new \InvalidArgumentException('Invalid mailbox reference.'); }
        $mailbox = $this->open($connection);
        try {
            $inbox = $mailbox->inbox(); $status = $inbox->status();
            if ((string) ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? '') !== $parts[1]) { throw new \RuntimeException('Mailbox changed. Sync again.'); }
            $message = $inbox->messages()->withHeaders()->withBody()->leaveUnread()->find((int) $parts[2]);
            if (!$message instanceof MessageInterface) { throw new \RuntimeException('Message no longer available.'); }
            return mb_substr($message->text() ?? 'This message has no plain-text part. View it in your mail provider.', 0, 20000);
        } finally { $mailbox->disconnect(); }
    }
    private function open(BusinessMailConnection $connection): Mailbox
    {
        $settings = $connection->getSettings();
        $mailbox = new Mailbox(['host' => $settings['imapHost'], 'username' => $settings['username'], 'password' => $this->vault->decrypt($connection->getEncryptedPassword(), 'business-mail'), 'debug' => false]);
        $mailbox->connect(new ImapConnection(new PinnedImapStream($this->policy)));
        return $mailbox;
    }
}
