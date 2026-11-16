<?php
declare(strict_types=1);
namespace App\Tests\Service;

use App\Service\GmailOutreachProvider;
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GmailOutreachProviderTest extends TestCase
{
    private function provider(array $responses, array &$history): GmailOutreachProvider
    {
        $stack = HandlerStack::create(new MockHandler($responses)); $stack->push(Middleware::history($history));
        return new GmailOutreachProvider('client-id', 'secret-for-mock', 'http://127.0.0.1:8000/outreach/gmail/callback', new Client(['handler' => $stack]));
    }
    private function json(array $data): Response { return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR)); }
    public function testRecentOnlyReadsMetadataWithoutMarkingRead(): void
    {
        $history = [];
        $provider = $this->provider([$this->json(['access_token' => 'mock', 'expires_in' => 3600]), $this->json(['messages' => [['id' => 'abc123']]]), $this->json(['id' => 'abc123', 'threadId' => 'thread123', 'payload' => ['headers' => [['name' => 'Subject', 'value' => 'Hello'], ['name' => 'From', 'value' => 'sender@example.test']]]])], $history);
        $rows = $provider->recent('mock-refresh');
        self::assertSame('Hello', $rows[0]['subject']);
        self::assertSame('thread123', $rows[0]['thread']);
        self::assertSame('GET', $history[1]['request']->getMethod());
        self::assertStringContainsString('format=metadata', (string) $history[2]['request']->getUri());
        self::assertCount(3, $history);
    }
    public function testReplyCreatesDraftWithSafeThreadHeadersAndNeverSends(): void
    {
        $history = [];
        $provider = $this->provider([$this->json(['access_token' => 'mock', 'expires_in' => 3600]), $this->json(['id' => 'draft123'])], $history);
        self::assertSame('draft123', $provider->createDraft('refresh', 'owner@example.test', 'Re: Hello', 'Reply body', 'thread123', '<parent@example.test>'));
        $request = $history[1]['request'];
        self::assertStringEndsWith('/users/me/drafts', $request->getUri()->getPath());
        $json = json_decode((string) $request->getBody(), true);
        self::assertSame('thread123', $json['message']['threadId']);
        $mime = base64_decode(strtr($json['message']['raw'], '-_', '+/'));
        self::assertStringContainsString('In-Reply-To: <parent@example.test>', $mime);
        self::assertStringContainsString('To: owner@example.test', $mime);
    }
    public function testBodyIgnoresHtmlAndAttachments(): void
    {
        $history = [];
        $provider = $this->provider([$this->json(['access_token' => 'mock', 'expires_in' => 3600]), $this->json(['sizeEstimate' => 100]), $this->json(['payload' => ['mimeType' => 'multipart/alternative', 'parts' => [
            ['mimeType' => 'text/plain', 'body' => ['data' => base64_encode('Plain content')]],
            ['mimeType' => 'text/html', 'body' => ['data' => base64_encode('<img src="https://tracker.example">')]],
            ['mimeType' => 'text/plain', 'filename' => 'secret.txt', 'body' => ['data' => base64_encode('attachment')]],
        ]]])], $history);
        self::assertSame('Plain content', $provider->body('refresh', 'abc123'));
    }
    public function testRevokedTokenFailsBeforeReadingInbox(): void
    {
        $history = [];
        $provider = $this->provider([new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}')], $history);
        $this->expectException(\RuntimeException::class);
        $provider->recent('revoked');
    }
    public function testPartialConsentIsRejected(): void
    {
        $history = [];
        $provider = $this->provider([$this->json(['access_token' => 'mock', 'refresh_token' => 'refresh', 'scope' => 'https://www.googleapis.com/auth/gmail.compose']), $this->json(['emailAddress' => 'owner@example.test'])], $history);
        $this->expectException(\RuntimeException::class);
        $provider->exchangeCode('code');
    }
    public function testOversizeMessageFailsBeforeFetchingBody(): void
    {
        $history = [];
        $provider = $this->provider([$this->json(['access_token' => 'mock']), $this->json(['sizeEstimate' => 999999])], $history);
        $this->expectException(\RuntimeException::class);
        $provider->body('refresh', 'abc123');
    }
}
