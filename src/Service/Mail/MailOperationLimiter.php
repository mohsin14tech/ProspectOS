<?php
declare(strict_types=1);
namespace App\Service\Mail;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class MailOperationLimiter
{
    public function __construct(private readonly RateLimiterFactory $mailboxLimiter) {}
    public function consume(string $identity): void
    {
        if (!$this->mailboxLimiter->create($identity)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(3600, 'Mailbox action limit reached (30 per hour). Try again later.');
        }
    }
}
