<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicIpResolverInterface;
use App\Service\PublicUrlPolicy;
use App\Service\RobotsTxtFetcher;
use App\Service\RobotsTxtPolicy;
use App\Service\HostAccessGateInterface;
use App\Service\WebsiteInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WebsiteInspector::class)]
final class WebsiteInspectorTest extends TestCase
{
    public function testExtractsOnlyPublicPageMetadataAndText(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('<html><head><title>Acme</title><meta name="description" content="A public company page"></head><body><script>secret()</script><p>Visible copy</p></body></html>', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html; charset=utf-8'],
            ]),
        ]);
        $inspector = $this->inspector($client);

        $result = $inspector->inspect('https://example.com/');

        self::assertSame('Acme', $result['title']);
        self::assertSame('A public company page', $result['description']);
        self::assertStringContainsString('Visible copy', $result['excerpt']);
        self::assertStringNotContainsString('secret()', $result['excerpt']);
    }

    public function testRedirectToPrivateAddressIsRejectedBeforeASecondRequest(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404]), new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location: http://127.0.0.1/admin'],
        ])]);
        $inspector = $this->inspector($client);

        $this->expectException(\InvalidArgumentException::class);
        $inspector->inspect('https://example.com/');
    }

    public function testNonHtmlContentIsRejected(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404]), new MockResponse('binary', [
            'http_code' => 200,
            'response_headers' => ['content-type: application/octet-stream'],
        ])]);
        $inspector = $this->inspector($client);

        $this->expectException(\RuntimeException::class);
        $inspector->inspect('https://example.com/');
    }

    public function testOversizedResponseIsCancelled(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 404]), new MockResponse(str_repeat('x', 1_000_001), [
            'http_code' => 200,
            'response_headers' => ['content-type: text/html'],
        ])]);
        $inspector = $this->inspector($client);

        $this->expectException(\RuntimeException::class);
        $inspector->inspect('https://example.com/');
    }

    public function testDisallowedPathStopsBeforePageFetch(): void
    {
        $client = new MockHttpClient([new MockResponse("User-agent: *\nDisallow: /private", [
            'http_code' => 200,
            'response_headers' => ['content-type: text/plain; charset=utf-8'],
        ])]);
        $inspector = $this->inspector($client);

        try {
            $inspector->inspect('https://example.com/private/contact');
            self::fail('A path disallowed by robots.txt should not be fetched.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('disallowed', $exception->getMessage());
            self::assertSame(1, $client->getRequestsCount());
        }
    }

    public function testUnreachableRobotsFileFailsClosed(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 503])]);
        $inspector = $this->inspector($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Page access was blocked.');
        $inspector->inspect('https://example.com/');
    }

    public function testRobotsRedirectToPrivateAddressIsRejected(): void
    {
        $client = new MockHttpClient([new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location: http://127.0.0.1/robots.txt'],
        ])]);
        $inspector = $this->inspector($client);

        $this->expectException(\InvalidArgumentException::class);
        $inspector->inspect('https://example.com/');
    }

    private function inspector(MockHttpClient $client): WebsiteInspector
    {
        $policy = new PublicUrlPolicy(new WebsiteFixtureResolver(['example.com' => ['93.184.216.34']]));
        $gate = new ImmediateWebsiteHostGate();

        return new WebsiteInspector($client, $policy, new RobotsTxtFetcher($client, $policy, $gate, new RobotsTxtPolicy()), $gate);
    }
}

final class ImmediateWebsiteHostGate implements HostAccessGateInterface
{
    public function run(string $host, callable $operation): mixed
    {
        return $operation();
    }
}

final class WebsiteFixtureResolver implements PublicIpResolverInterface
{
    /** @param array<string, list<string>> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function resolve(string $host): array
    {
        return $this->records[$host] ?? [];
    }
}
