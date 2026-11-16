<?php
declare(strict_types=1);
namespace App\Service\Mail;

interface GmailGatewayInterface
{
    public function isConfigured(): bool;
    public function authorizationUrl(string $state): string;
    public function exchangeCode(string $code): array;
    public function createDraft(string $refreshToken, string $recipient, string $subject, string $body, ?string $threadId = null, ?string $replyMessageId = null): string;
    public function recent(string $refreshToken): array;
    public function body(string $refreshToken, string $id): string;
}
