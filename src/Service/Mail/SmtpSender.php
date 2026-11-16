<?php
declare(strict_types=1);
namespace App\Service\Mail;
use App\Entity\BusinessMailConnection;
use Symfony\Component\Mime\Email;
final class SmtpSender implements EmailTransportInterface
{
    public function __construct(private readonly SmtpTransportFactory $factory) {}
    public function send(BusinessMailConnection $connection, array $payload, string $idempotencyKey): string
    {
        $transport = $this->factory->create($connection);
        $email = (new Email())->from($payload['from'])->to(...$payload['to'])->subject($payload['subject'])->text($payload['text']);
        // Message-ID is stable for audit; SMTP does not promise idempotency.
        $email->getHeaders()->addIdHeader('Message-ID', $idempotencyKey.'@prospectos.local');
        try {
            $sent = $transport->send($email);
            if ($sent === null) { throw new \RuntimeException('SMTP did not confirm acceptance.'); }
            return $sent->getMessageId();
        } finally { $transport->stop(); }
    }
}
