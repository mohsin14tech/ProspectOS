<?php
declare(strict_types=1);
namespace App\Service\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/** A mailbox connection must authenticate; anonymous relay acceptance is not a login test. */
final class AuthenticatedSmtpTransport extends EsmtpTransport
{
    public function start(): void
    {
        parent::start();
        try { $authenticated = $this->getUsername() !== '' && isset($this->getCapabilities()['AUTH']); }
        catch (\Error) { $authenticated = false; } // HELO fallback has no ESMTP capabilities.
        if (!$authenticated) {
            $this->stop();
            throw new TransportException('The mailbox server must support authenticated ESMTP.');
        }
    }
}
