<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\Message;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Gmail integration is draft-only: ProspectOS never invokes drafts.send or messages.send. */
final class GmailOutreachProvider implements \App\Service\Mail\GmailGatewayInterface
{
    private const SCOPE = 'https://www.googleapis.com/auth/gmail.compose';
    private const READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    private const REDIRECTS = [
        'http://localhost:8000/outreach/gmail/callback',
        'http://127.0.0.1:8000/outreach/gmail/callback',
        'https://prospectos.14technologies.com/outreach/gmail/callback',
    ];

    public function __construct(
        #[Autowire(env: 'default::GOOGLE_MAIL_CLIENT_ID')]
        private readonly ?string $clientId,
        #[Autowire(env: 'default::GOOGLE_MAIL_CLIENT_SECRET')]
        private readonly ?string $clientSecret,
        #[Autowire(env: 'default::GOOGLE_MAIL_REDIRECT_URI')]
        private readonly ?string $redirectUri,
        private readonly ?\GuzzleHttp\ClientInterface $httpClient = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->clientId ?? '') !== '' && trim($this->clientSecret ?? '') !== '' && in_array($this->redirectUri, self::REDIRECTS, true);
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->client();
        $client->setState($state);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client->createAuthUrl([self::SCOPE, self::READ_SCOPE], ['include_granted_scopes' => 'false']);
    }

    /** @return array{email: string, refreshToken: string} */
    public function exchangeCode(string $code): array
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!is_array($token) || isset($token['error']) || !is_string($token['refresh_token'] ?? null) || $token['refresh_token'] === '') {
            throw new \RuntimeException('Google did not grant an offline Gmail connection. Reconnect and approve the requested access.');
        }

        $client->setAccessToken($token);
        $profile = (new Gmail($client))->users->getProfile('me');
        $email = $profile->getEmailAddress();
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Gmail did not return a valid connected account address.');
        }

        $scopes = explode(' ', (string) ($token['scope'] ?? ''));
        if (!in_array(self::SCOPE, $scopes, true) || !in_array(self::READ_SCOPE, $scopes, true)) {
            throw new \RuntimeException('Gmail did not grant both inbox and draft access. Reconnect and review the permissions.');
        }
        return ['email' => mb_strtolower($email), 'refreshToken' => $token['refresh_token'], 'inboxAccess' => true];
    }

    public function createDraft(string $refreshToken, string $recipient, string $subject, string $body, ?string $threadId = null, ?string $replyMessageId = null): string
    {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/[\r\n]/', $recipient.$subject) === 1
            || trim($subject) === ''
            || mb_strlen($subject) > 900
            || trim($body) === ''
            || mb_strlen($body) > 20000
        ) {
            throw new \InvalidArgumentException('Enter a valid recipient, a one-line subject and a message between 1 and 20000 characters.');
        }

        $client = $this->client();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($token) || isset($token['error']) || !is_string($token['access_token'] ?? null)) {
            throw new \RuntimeException('Gmail authorization expired. Reconnect the mailbox and try again.');
        }
        $client->setAccessToken($token);

        $mime = 'To: '.$recipient."\r\n"
            .'Subject: '.mb_encode_mimeheader($subject, 'UTF-8')."\r\n"
            ."MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            .chunk_split(base64_encode($body), 76, "\r\n");
        $message = new Message();
        if ($threadId !== null) {
            if (!preg_match('/^[a-zA-Z0-9_-]{1,200}$/', $threadId)) { throw new \InvalidArgumentException('Invalid Gmail thread reference.'); }
            $message->setThreadId($threadId);
            if ($replyMessageId !== null && preg_match('/^<[^<>\r\n\x00-\x20]{1,250}>$/', $replyMessageId)) {
                $mime = 'In-Reply-To: '.$replyMessageId."\r\n".'References: '.$replyMessageId."\r\n".$mime;
            }
        }
        $message->setRaw(rtrim(strtr(base64_encode($mime), '+/', '-_'), '='));
        $draft = (new Gmail($client))->users_drafts->create('me', new Draft(['message' => $message]));
        $id = $draft->getId();
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Gmail accepted the request without returning a draft ID.');
        }

        return $id;
    }

    private function client(): Client
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Gmail OAuth is not configured for this environment.');
        }

        $client = new Client();
        $client->setHttpClient($this->httpClient ?? new \GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false]));
        $client->setClientId((string) $this->clientId);
        $client->setClientSecret((string) $this->clientSecret);
        $client->setRedirectUri((string) $this->redirectUri);
        $client->setApplicationName('ProspectOS');

        return $client;
    }

    public function recent(string $refreshToken): array
    {
        $gmail = $this->gmail($refreshToken);
        $list = $gmail->users_messages->listUsersMessages('me', ['labelIds' => ['INBOX'], 'maxResults' => 10]);
        $rows = [];
        foreach ($list->getMessages() ?? [] as $entry) {
            $message = $gmail->users_messages->get('me', $entry->getId(), ['format' => 'metadata', 'metadataHeaders' => ['From','Subject','Date','Message-ID']]);
            $headers = [];
            foreach ($message->getPayload()?->getHeaders() ?? [] as $header) { $headers[strtolower($header->getName())] = $header->getValue(); }
            $rows[] = ['id' => $message->getId(), 'thread' => $message->getThreadId(), 'subject' => $headers['subject'] ?? '(No subject)', 'sender' => $headers['from'] ?? '', 'date' => $headers['date'] ?? '', 'messageId' => $headers['message-id'] ?? '', 'preview' => $message->getSnippet() ?? ''];
        }
        return $rows;
    }

    public function body(string $refreshToken, string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,200}$/', $id)) { throw new \InvalidArgumentException('Invalid Gmail message reference.'); }
        $gmail = $this->gmail($refreshToken);
        $metadata = $gmail->users_messages->get('me', $id, ['format' => 'minimal']);
        if ($metadata->getSizeEstimate() > 262144) { throw new \RuntimeException('Open messages larger than 256 KB in Gmail.'); }
        $message = $gmail->users_messages->get('me', $id, ['format' => 'full']);
        $parts = [$message->getPayload()]; $text = ''; $examined = 0;
        while ($parts && ++$examined <= 50) {
            $part = array_shift($parts);
            if (!$part || $part->getFilename()) { continue; }
            if ($part->getMimeType() === 'text/plain') {
                $data = (string) $part->getBody()?->getData();
                if (strlen($data) <= 350000) { $text .= (string) base64_decode(strtr($data, '-_', '+/'), true); }
            }
            array_push($parts, ...($part->getParts() ?? []));
        }
        return mb_substr(trim($text) ?: 'No plain-text body is available. Open this message in Gmail.', 0, 20000);
    }

    private function gmail(string $refreshToken): Gmail
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($token) || isset($token['error']) || empty($token['access_token'])) { throw new \RuntimeException('Reconnect Gmail.'); }
        $client->setAccessToken($token);
        return new Gmail($client);
    }
}
