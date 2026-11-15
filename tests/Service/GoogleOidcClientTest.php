<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\GoogleOidcClient;
use PHPUnit\Framework\TestCase;

final class GoogleOidcClientTest extends TestCase
{
    public function testAuthorizationUrlUsesOnlyIdentityScopesAndFreshStateAndNonce(): void
    {
        $client = new GoogleOidcClient('client-id.apps.googleusercontent.com', 'client-secret', 'http://127.0.0.1:8000/login/google/callback');
        $url = $client->authorizationUrl(str_repeat('a', 64), str_repeat('b', 64));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        self::assertSame('code', $query['response_type']);
        self::assertSame('openid email', $query['scope']);
        self::assertSame(str_repeat('a', 64), $query['state']);
        self::assertSame(str_repeat('b', 64), $query['nonce']);
        self::assertSame('http://127.0.0.1:8000/login/google/callback', $query['redirect_uri']);
        self::assertStringNotContainsString('gmail', strtolower($url));
    }

    public function testRedirectUriMustBeAnExactAllowlistedValue(): void
    {
        $client = new GoogleOidcClient('client-id', 'client-secret', 'https://attacker.invalid/callback');

        self::assertFalse($client->isConfigured());
        $this->expectException(\RuntimeException::class);
        $client->authorizationUrl('state', 'nonce');
    }

    public function testUnconfiguredProviderFailsClosed(): void
    {
        $client = new GoogleOidcClient('', '', 'http://127.0.0.1:8000/login/google/callback');

        self::assertFalse($client->isConfigured());
        try {
            $client->exchangeAndVerify('authorization-code');
            self::fail('The unconfigured client must not exchange authorization codes.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Google sign-in is not configured.', $exception->getMessage());
        }
    }
}
