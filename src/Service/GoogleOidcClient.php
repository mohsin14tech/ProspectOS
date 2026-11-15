<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GoogleOidcClient
{
    private const ALLOWED_REDIRECT_URIS = [
        'http://localhost:8000/login/google/callback',
        'http://127.0.0.1:8000/login/google/callback',
        'https://prospectos.14technologies.com/login/google/callback',
    ];

    public function __construct(
        #[Autowire(env: 'string:GOOGLE_OIDC_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'string:GOOGLE_OIDC_CLIENT_SECRET')]
        private readonly string $clientSecret,
        #[Autowire(env: 'string:GOOGLE_OIDC_REDIRECT_URI')]
        private readonly string $redirectUri,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->clientId)
            && '' !== trim($this->clientSecret)
            && in_array($this->redirectUri, self::ALLOWED_REDIRECT_URIS, true);
    }

    public function authorizationUrl(string $state, string $nonce): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google sign-in is not configured.');
        }

        $client = $this->client();
        $client->setState($state);

        return $client->createAuthUrl(['openid', 'email'], ['nonce' => $nonce]);
    }

    /** @return array<string, mixed> */
    public function exchangeAndVerify(string $code): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google sign-in is not configured.');
        }

        $client = $this->client();
        $response = $client->fetchAccessTokenWithAuthCode($code);
        if (!is_array($response) || isset($response['error']) || !is_string($response['id_token'] ?? null)) {
            throw new \RuntimeException('Google authorization code exchange failed.');
        }

        $claims = $client->verifyIdToken($response['id_token']);
        if (!is_array($claims)
            || !is_string($claims['sub'] ?? null)
            || '' === $claims['sub']
            || !in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            || !is_string($claims['email'] ?? null)
            || !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            || true !== ($claims['email_verified'] ?? false)
            || !is_int($claims['exp'] ?? null)
            || $claims['exp'] <= time()
        ) {
            throw new \RuntimeException('Google identity token claims are invalid.');
        }

        return $claims;
    }

    private function client(): Client
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setPrompt('select_account');

        return $client;
    }
}
