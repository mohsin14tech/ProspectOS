<?php
declare(strict_types=1);
namespace App\Service\Mail;

use App\Service\PublicUrlPolicy;
use DirectoryTree\ImapEngine\Connection\Streams\ImapStream;

/** DNS pinning, TLS identity verification and hard response/time budgets for IMAP. */
final class PinnedImapStream extends ImapStream
{
    private int $remaining = 1048576;
    private float $deadline;
    public function __construct(private readonly PublicUrlPolicy $policy) { $this->deadline = microtime(true) + 25; }
    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
    {
        if ($port !== 993 || $transport !== 'ssl') { throw new \RuntimeException('IMAP requires TLS on port 993.'); }
        $target = $this->policy->validateAndPin('https://'.$host.'/');
        $options['ssl'] = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $target['host'], 'SNI_enabled' => true, 'allow_self_signed' => false];
        $ok = parent::open('ssl', str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'], 993, 5, $options);
        $this->setTimeout(5);
        return $ok;
    }
    public function read(int $length): string|false
    {
        if ($length > $this->remaining || $length < 0) { throw new \RuntimeException('Mailbox response exceeds the read limit.'); }
        $result = '';
        while (strlen($result) < $length) {
            $this->check();
            $chunk = fread($this->stream, min(8192, $length - strlen($result)));
            if ($chunk === false || $chunk === '') { throw new \RuntimeException('Mailbox read timed out or ended early.'); }
            $result .= $chunk;
        }
        $this->remaining -= strlen($result);
        return $result;
    }
    public function fgets(): string|false
    {
        $this->check();
        $line = fgets($this->stream, min(65536, $this->remaining + 1));
        if ($line === false || !str_ends_with($line, "\n")) { throw new \RuntimeException('Mailbox returned an incomplete or oversized line.'); }
        $this->remaining -= strlen($line);
        return $line;
    }
    public function fwrite(string $data): int|false { $this->check(); return parent::fwrite($data); }
    private function check(): void
    {
        if ($this->remaining < 2 || microtime(true) > $this->deadline || !$this->opened()) { throw new \RuntimeException('Mailbox read limit reached.'); }
    }
}
