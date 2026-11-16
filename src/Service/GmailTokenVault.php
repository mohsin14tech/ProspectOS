<?php

declare(strict_types=1);

namespace App\Service;

/** Encrypts refresh tokens using an application key that is kept outside the database. */
final class GmailTokenVault
{
    public function __construct(private readonly SecretVault $vault)
    {
    }

    public function encrypt(string $token): string
    {
        return $this->vault->encrypt($token, 'gmail-refresh-token');
    }

    public function decrypt(string $value): string
    {
        try {
            return $this->vault->decrypt($value, 'gmail-refresh-token');
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException('The saved Gmail credential cannot be decrypted. Reconnect the mailbox.', 0, $exception);
        }
    }
}
