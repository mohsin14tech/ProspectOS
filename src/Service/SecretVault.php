<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Application-level authenticated encryption for provider credentials stored in the database. */
final class SecretVault
{
    private readonly string $applicationSecret;

    public function __construct(#[Autowire('%kernel.secret%')] string $applicationSecret)
    {
        if ($applicationSecret === '') {
            throw new \RuntimeException('APP_SECRET is required to protect provider credentials.');
        }

        $this->applicationSecret = $applicationSecret;
    }

    public function encrypt(string $value, string $purpose): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($value, $nonce, $this->key($purpose));

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encrypted, string $purpose): string
    {
        $payload = base64_decode($encrypted, true);
        if (!is_string($payload) || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('The saved provider credential cannot be decrypted. Save it again.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $value = sodium_crypto_secretbox_open(substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key($purpose));
        if (!is_string($value)) {
            throw new \RuntimeException('The saved provider credential cannot be decrypted. Check APP_SECRET and save it again.');
        }

        return $value;
    }

    private function key(string $purpose): string
    {
        if ($purpose === '' || mb_strlen($purpose) > 100) {
            throw new \InvalidArgumentException('A bounded credential purpose is required.');
        }

        return hash_hmac('sha256', 'prospectos:credential:v1:'.$purpose, $this->applicationSecret, true);
    }
}
