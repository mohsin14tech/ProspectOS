<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Controller\BusinessMailController;
use PHPUnit\Framework\TestCase;

final class MailboxSetupHelpersTest extends TestCase
{
    public function testGoogleAppPasswordSpacesAreRemoved(): void
    {
        self::assertSame('abcdefghijklmnop', BusinessMailController::normalizePassword(' abcd efgh ijkl mnop ', 'imap.gmail.com'));
        self::assertSame('abcdefghijklmnop', BusinessMailController::normalizePassword("abcd\u{00A0}efgh ijkl mnop", 'IMAP.GMAIL.COM'));
    }

    public function testOtherProvidersKeepThePasswordExactly(): void
    {
        self::assertSame(' pass word ', BusinessMailController::normalizePassword(' pass word ', 'imap.mail.yahoo.com'));
    }

    public function testAuthenticationFailuresGetProviderSpecificAdvice(): void
    {
        $gmail = BusinessMailController::explainFailure(new \RuntimeException('', 0, new \RuntimeException('NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)')), 'imap.gmail.com');
        self::assertStringContainsString('app password', $gmail);
        self::assertStringContainsString('apppasswords', $gmail);

        $smtp = BusinessMailController::explainFailure(new \RuntimeException('Expected response code "235" but got code "535"'), 'mail.example.com');
        self::assertStringContainsString('rejected the username or password', $smtp);
    }

    public function testNetworkFailuresPointAtServerSettingsWithoutEchoingDetails(): void
    {
        $hint = BusinessMailController::explainFailure(new \RuntimeException('Connection timed out to secret-host:993 user=bob'), 'imap.gmail.com');
        self::assertStringContainsString('Could not reach the mail server', $hint);
        self::assertStringNotContainsString('bob', $hint);
    }
}
