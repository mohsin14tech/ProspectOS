<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SecretVault;
use PHPUnit\Framework\TestCase;

final class SecretVaultTest extends TestCase
{
    public function testEncryptedValuesRequireTheSameApplicationSecretAndPurpose(): void
    {
        $vault = new SecretVault('application-secret-for-test');
        $ciphertext = $vault->encrypt('test-api-key-value', 'ai-provider:gemini');

        self::assertNotSame('test-api-key-value', $ciphertext);
        self::assertSame('test-api-key-value', $vault->decrypt($ciphertext, 'ai-provider:gemini'));
        $this->expectException(\RuntimeException::class);
        $vault->decrypt($ciphertext, 'gmail-refresh-token');
    }
}
