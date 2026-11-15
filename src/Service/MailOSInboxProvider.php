<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Read-only MailOS JMAP adapter. It never requests message bodies or attachments. */
final class MailOSInboxProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'string:MAILOS_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire(env: 'string:MAILOS_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'string:MAILOS_MAILBOX_ID')]
        private readonly string $mailboxId,
        #[Autowire(env: 'string:MAILOS_READ_ONLY_SCOPE_CONFIRMED')]
        private readonly string $readOnlyScopeConfirmed = 'false',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey) && '' !== trim($this->mailboxId) && 'true' === strtolower(trim($this->readOnlyScopeConfirmed));
    }

    /** @return list<array{id: string, threadId: string, mailboxId: string, from: string, to: string, subject: string, receivedAt: \DateTimeImmutable, isRead: bool, threadCount: int}> */
    public function fetchInbox(int $limit = 25): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('MailOS read access is not configured.');
        }
        $limit = max(1, min(25, $limit));
        $base = rtrim($this->baseUrl, '/');
        $parts = parse_url($base);
        if (!is_array($parts) || 'https' !== ($parts['scheme'] ?? null) || !in_array(strtolower($parts['host'] ?? ''), ['mail.14technologies.com'], true)) {
            throw new \RuntimeException('MailOS base URL must use the verified MailOS HTTPS origin.');
        }

        $headers = ['Authorization' => 'Bearer '.$this->apiKey, 'Accept' => 'application/json'];
        // MailOS documents /.well-known/jmap as a redirect to /jmap/session.
        $session = $this->requestJson('GET', $base.'/jmap/session', $headers);
        $apiUrl = $session['apiUrl'] ?? null;
        $accountId = $session['primaryAccounts']['urn:ietf:params:jmap:mail'] ?? null;
        if (!is_string($apiUrl) || !is_string($accountId) || '' === $accountId) {
            throw new \RuntimeException('MailOS did not return a usable JMAP mail session.');
        }
        $apiParts = parse_url($apiUrl);
        if (!is_array($apiParts) || 'https' !== ($apiParts['scheme'] ?? null) || strtolower($apiParts['host'] ?? '') !== strtolower($parts['host'])) {
            throw new \RuntimeException('MailOS returned an API URL outside its verified origin.');
        }

        $query = $this->requestJson('POST', $apiUrl, $headers + ['Content-Type' => 'application/json'], [
            'using' => ['urn:ietf:params:jmap:core', 'urn:ietf:params:jmap:mail'],
            'methodCalls' => [
                [['accountId' => $accountId, 'filter' => ['inMailbox' => $this->mailboxId], 'sort' => [['property' => 'receivedAt', 'isAscending' => false]], 'limit' => $limit], 'Email/query', 'inbox'],
            ],
        ]);
        $queryResult = $this->methodResult($query, 'Email/query', 'inbox');
        $ids = $queryResult['ids'] ?? [];
        if (!is_array($ids) || [] === $ids) {
            return [];
        }

        $details = $this->requestJson('POST', $apiUrl, $headers + ['Content-Type' => 'application/json'], [
            'using' => ['urn:ietf:params:jmap:core', 'urn:ietf:params:jmap:mail'],
            'methodCalls' => [
                [['accountId' => $accountId, 'ids' => array_slice($ids, 0, $limit), 'properties' => ['id', 'threadId', 'mailboxIds', 'from', 'to', 'subject', 'receivedAt', 'keywords']], 'Email/get', 'headers'],
            ],
        ]);
        $emails = $this->methodResult($details, 'Email/get', 'headers')['list'] ?? [];
        if (!is_array($emails)) {
            throw new \RuntimeException('MailOS returned an invalid inbox response.');
        }

        $threadIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): ?string => is_array($email) && is_string($email['threadId'] ?? null) ? $email['threadId'] : null,
            $emails,
        ))));
        $threadCounts = [];
        if ([] !== $threadIds) {
            $threadResponse = $this->requestJson('POST', $apiUrl, $headers + ['Content-Type' => 'application/json'], [
                'using' => ['urn:ietf:params:jmap:core', 'urn:ietf:params:jmap:mail'],
                'methodCalls' => [[['accountId' => $accountId, 'ids' => array_slice($threadIds, 0, $limit), 'properties' => ['id', 'emailIds']], 'Thread/get', 'threads']],
            ]);
            foreach ($this->methodResult($threadResponse, 'Thread/get', 'threads')['list'] ?? [] as $thread) {
                if (is_array($thread) && is_string($thread['id'] ?? null) && is_array($thread['emailIds'] ?? null)) {
                    $threadCounts[$thread['id']] = count($thread['emailIds']);
                }
            }
        }

        $messages = [];
        foreach (array_slice($emails, 0, $limit) as $email) {
            if (!is_array($email) || !is_string($email['id'] ?? null) || !is_string($email['threadId'] ?? null)) {
                continue;
            }
            $date = is_string($email['receivedAt'] ?? null) ? new \DateTimeImmutable($email['receivedAt']) : new \DateTimeImmutable();
            $messages[] = [
                'id' => mb_substr($email['id'], 0, 160),
                'threadId' => mb_substr($email['threadId'], 0, 160),
                'mailboxId' => $this->mailboxId,
                'from' => $this->addressList($email['from'] ?? []),
                'to' => $this->addressList($email['to'] ?? []),
                'subject' => mb_substr(is_string($email['subject'] ?? null) ? $email['subject'] : '(no subject)', 0, 998),
                'receivedAt' => $date,
                'isRead' => isset($email['keywords']['$seen']),
                'threadCount' => $threadCounts[$email['threadId']] ?? 1,
            ];
        }

        return $messages;
    }

    /** @param array<string, string> $headers @param array<string, mixed>|null $body @return array<string, mixed> */
    private function requestJson(string $method, string $url, array $headers, ?array $body = null): array
    {
        try {
            $response = $this->httpClient->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'max_redirects' => 0,
                'timeout' => 8,
                'max_duration' => 12,
                'buffer' => true,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(401 === $status || 403 === $status
                    ? 'MailOS rejected the read-only API key or mailbox scope.'
                    : sprintf('MailOS request failed with HTTP %d.', $status));
            }
            $decoded = json_decode($response->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('MailOS returned an invalid response.');
            }

            return $decoded;
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface) {
            throw new \RuntimeException('MailOS could not be reached. Check the connection and try again.');
        } catch (\JsonException) {
            throw new \RuntimeException('MailOS returned an unreadable response.');
        }
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function methodResult(array $response, string $method, string $tag): array
    {
        foreach ($response['methodResponses'] ?? [] as $result) {
            if (is_array($result) && ($result[0] ?? null) === $method && ($result[2] ?? null) === $tag && is_array($result[1] ?? null)) {
                return $result[1];
            }
        }

        throw new \RuntimeException('MailOS could not complete the inbox read request.');
    }

    /** @param mixed $addresses */
    private function addressList(mixed $addresses): string
    {
        if (!is_array($addresses)) {
            return '';
        }
        $items = [];
        foreach (array_slice($addresses, 0, 10) as $address) {
            if (!is_array($address)) {
                continue;
            }
            $email = is_string($address['email'] ?? null) ? $address['email'] : '';
            $name = is_string($address['name'] ?? null) ? $address['name'] : '';
            if ('' !== $email) {
                $items[] = mb_substr('' !== $name ? $name.' <'.$email.'>' : $email, 0, 320);
            }
        }

        return implode(', ', $items);
    }
}
