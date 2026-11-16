<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\GmailTokenVault;
use App\Service\SecretVault;
use PHPUnit\Framework\TestCase;

final class GmailTokenVaultTest extends TestCase
{
    public function testRefreshTokenIsEncryptedAndCanBeRecovered(): void
    {
        $vault = new GmailTokenVault(new SecretVault('application-secret-for-test'));
        $encrypted = $vault->encrypt('refresh-token-value');

        self::assertNotSame('refresh-token-value', $encrypted);
        self::assertStringNotContainsString('refresh-token-value', $encrypted);
        self::assertSame('refresh-token-value', $vault->decrypt($encrypted));
    }

    public function testRefreshTokenCannotBeReadWithDifferentApplicationSecret(): void
    {
        $encrypted = (new GmailTokenVault(new SecretVault('first-app-secret')))->encrypt('refresh-token-value');

        $this->expectException(\RuntimeException::class);
        (new GmailTokenVault(new SecretVault('different-app-secret')))->decrypt($encrypted);
    }
}
