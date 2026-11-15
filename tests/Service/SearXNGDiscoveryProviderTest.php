<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicIpResolverInterface;
use App\Service\PublicUrlPolicy;
use App\Service\SearXNGDiscoveryProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SearXNGDiscoveryProvider::class)]
final class SearXNGDiscoveryProviderTest extends TestCase
{
    public function testReturnsUniquePublicSourceBackedCandidates(): void
    {
        $payload = ['results' => [
            ['title' => 'Acme &amp; Sons', 'url' => 'https://acme.example/about', 'content' => 'Public website result', 'engine' => 'Example Engine'],
            ['title' => 'Another page on the same site', 'url' => 'https://acme.example/contact'],
            ['title' => 'Internal result', 'url' => 'http://127.0.0.1/admin'],
            ['title' => 'Useful directory result', 'url' => 'https://directory.example/listing', 'content' => 'A directory result.'],
        ]];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($payload): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://searxng:8080/search', $url);
            self::assertStringContainsString('format=json', $options['body']);

            return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });
        $provider = new SearXNGDiscoveryProvider($client, new PublicUrlPolicy(new SearchFixtureResolver()), 'http://searxng:8080');

        $results = $provider->discover('Find local businesses');

        self::assertCount(2, $results);
        self::assertSame('Acme & Sons', $results[0]['name']);
        self::assertSame('Web search candidate', $results[0]['category']);
        self::assertSame('Not verified', $results[0]['location']);
        self::assertSame('search_result', $results[0]['verificationState']);
        self::assertSame('https://acme.example/about', $results[0]['sourceUrl']);
        self::assertSame('web_search', $provider->sourceMode());
    }

    public function testSurfacesRateLimitAndDisabledJsonResponses(): void
    {
        foreach ([429, 403] as $status) {
            $provider = new SearXNGDiscoveryProvider(
                new MockHttpClient([new MockResponse('', ['http_code' => $status])]),
                new PublicUrlPolicy(new SearchFixtureResolver()),
                'http://searxng:8080',
            );
            try {
                $provider->discover('Find local businesses');
                self::fail('Expected the search API error to be reported.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString($status === 429 ? 'rate limited' : 'rejected JSON', $exception->getMessage());
            }
        }
    }

    public function testRejectsMalformedSearchApiPayload(): void
    {
        $provider = new SearXNGDiscoveryProvider(
            new MockHttpClient([new MockResponse('{"unexpected":true}', ['http_code' => 200])]),
            new PublicUrlPolicy(new SearchFixtureResolver()),
            'http://searxng:8080',
        );

        $this->expectException(\RuntimeException::class);
        $provider->discover('Find local businesses');
    }
}

final class SearchFixtureResolver implements PublicIpResolverInterface
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        return ['93.184.216.34'];
    }
}
