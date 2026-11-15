<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MailOSInboxProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MailOSInboxProvider::class)]
final class MailOSInboxProviderTest extends TestCase
{
    public function testFetchesBoundedHeaderOnlyInboxAndThreadState(): void
    {
        $responses = [
            new MockResponse(json_encode(['apiUrl' => 'https://mail.14technologies.com/jmap/', 'primaryAccounts' => ['urn:ietf:params:jmap:mail' => 'account-1']], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['methodResponses' => [['Email/query', ['ids' => ['message-1']], 'inbox']]], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['methodResponses' => [['Email/get', ['list' => [[
                'id' => 'message-1', 'threadId' => 'thread-1', 'mailboxIds' => ['mailbox-1' => true],
                'from' => [['name' => 'Maya Chen', 'email' => 'maya@example.test']],
                'to' => [['name' => null, 'email' => 'owner@example.test']], 'subject' => 'Question about the report',
                'receivedAt' => '2026-11-14T10:00:00Z', 'keywords' => ['$seen' => true],
            ]]], 'headers']]], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['methodResponses' => [['Thread/get', ['list' => [['id' => 'thread-1', 'emailIds' => ['message-1', 'message-2']]]], 'threads']]], JSON_THROW_ON_ERROR)),
        ];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = $options['headers'] ?? [];
            $serializedHeaders = is_array($headers)
                ? implode("\n", array_map(static fn (int|string $key, mixed $value): string => is_int($key) ? (string) $value : $key.': '.(is_array($value) ? implode(', ', $value) : (string) $value), array_keys($headers), array_values($headers)))
                : (string) $headers;
            self::assertStringContainsString('Bearer test-key', $serializedHeaders);
            if ('POST' === $method && str_contains($url, '/jmap/')) {
                $body = json_decode((string) $options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertLessThanOrEqual(25, $body['methodCalls'][0][1] === 'Email/query' ? $body['methodCalls'][0][0]['limit'] : 25);
                foreach ($body['methodCalls'] as $call) {
                    self::assertNotContains('bodyValues', $call[0]['properties'] ?? []);
                    self::assertNotContains('bodyStructure', $call[0]['properties'] ?? []);
                }
            }

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
        $provider = new MailOSInboxProvider($client, 'https://mail.14technologies.com', 'test-key', 'mailbox-1', 'true');

        $messages = $provider->fetchInbox(300);

        self::assertCount(1, $messages);
        self::assertSame('Maya Chen <maya@example.test>', $messages[0]['from']);
        self::assertTrue($messages[0]['isRead']);
        self::assertSame(2, $messages[0]['threadCount']);
        self::assertSame('thread-1', $messages[0]['threadId']);
    }

    public function testDoesNotCallProviderWhenCredentialsAreMissing(): void
    {
        $provider = new MailOSInboxProvider(new MockHttpClient(), 'https://mail.14technologies.com', '', '');
        self::assertFalse($provider->isConfigured());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured');
        $provider->fetchInbox();
    }

    public function testRejectsUnverifiedMailOSOrigin(): void
    {
        $provider = new MailOSInboxProvider(new MockHttpClient(), 'http://127.0.0.1', 'test-key', 'mailbox-1', 'true');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('verified MailOS HTTPS origin');
        $provider->fetchInbox();
    }
}
