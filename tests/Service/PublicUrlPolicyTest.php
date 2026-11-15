<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicIpResolverInterface;
use App\Service\PublicUrlPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicUrlPolicy::class)]
final class PublicUrlPolicyTest extends TestCase
{
    public function testPublicHostIsPinnedToItsValidatedIp(): void
    {
        $policy = new PublicUrlPolicy(new FixtureResolver(['example.com' => ['93.184.216.34']]));

        self::assertSame([
            'url' => 'https://example.com/about?q=1',
            'host' => 'example.com',
            'port' => 443,
            'ip' => '93.184.216.34',
        ], $policy->validateAndPin('https://example.com/about?q=1#section'));
    }

    public function testPrivateAndReservedDestinationsAreRejected(): void
    {
        $policy = new PublicUrlPolicy(new FixtureResolver([
            'internal.example' => ['93.184.216.34', '10.0.0.8'],
        ]));

        foreach (['http://127.0.0.1/', 'http://169.254.169.254/', 'http://metadata.google.internal/', 'http://example.com:8080/'] as $url) {
            try {
                $policy->validateAndPin($url);
                self::fail('Expected URL to be rejected: '.$url);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $policy->validateAndPin('https://internal.example/');
    }

    public function testOnlyHttpAndHttpsWithoutCredentialsAreAccepted(): void
    {
        $policy = new PublicUrlPolicy(new FixtureResolver([]));

        foreach (['file:///etc/passwd', 'https://user:pass@example.com/', 'ftp://example.com/'] as $url) {
            try {
                $policy->validateAndPin($url);
                self::fail('Expected URL to be rejected: '.$url);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}

final class FixtureResolver implements PublicIpResolverInterface
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
