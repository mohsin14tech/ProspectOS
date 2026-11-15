<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Search results are candidates from upstream engines, not verified business records. */
final class SearXNGDiscoveryProvider implements DiscoveryProviderInterface
{
    private const MAX_RESULTS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PublicUrlPolicy $urlPolicy,
        #[Autowire(env: 'SEARXNG_BASE_URL')]
        private readonly string $baseUrl,
    ) {
    }

    /** @return list<array{slug: string, name: string, category: string, location: string, findingType: string, claim: string, excerpt: string, dimensions: array<string, int|null>, sourceUrl: string, sourceTitle: string, retrievalMethod: string, verificationState: string, confidence: float}> */
    public function discover(string $objective): array
    {
        $query = trim($objective);
        if (mb_strlen($query) < 3 || mb_strlen($query) > 500) {
            throw new \InvalidArgumentException('The search objective must be between 3 and 500 characters.');
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/search', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'ProspectOS/0.1 (self-hosted business discovery)',
            ],
            'body' => http_build_query(['q' => $query, 'format' => 'json', 'language' => 'en', 'safesearch' => 1]),
            'timeout' => 8,
            'max_duration' => 12,
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $response->cancel();
            throw new \RuntimeException(match ($status) {
                403 => 'The search service rejected JSON results. Check its local settings.',
                429 => 'The search service or an upstream engine is rate limited. Try again later.',
                default => sprintf('The search service returned HTTP %d.', $status),
            });
        }

        try {
            $payload = json_decode($response->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The search service returned an unreadable response.', previous: $exception);
        }

        if (!is_array($payload) || !is_array($payload['results'] ?? null)) {
            throw new \RuntimeException('The search service response did not contain results.');
        }

        $results = [];
        $seenHosts = [];
        foreach ($payload['results'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim(html_entity_decode(strip_tags((string) ($item['title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = trim((string) ($item['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            try {
                $target = $this->urlPolicy->validateAndPin($url);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (isset($seenHosts[$target['host']])) {
                continue;
            }
            $seenHosts[$target['host']] = true;

            $snippet = trim(strip_tags((string) ($item['content'] ?? '')));
            $snippet = mb_substr(preg_replace('/\s+/u', ' ', $snippet) ?? '', 0, 500);
            $engine = trim((string) ($item['engine'] ?? ''));
            $sourceTitle = $engine === '' ? 'Web search result via SearXNG' : 'Web search result via '.mb_substr($engine, 0, 100);

            $results[] = [
                'slug' => 'search-'.substr(hash('sha256', $target['host']), 0, 32),
                'name' => mb_substr($title, 0, 180),
                'category' => 'Web search candidate',
                'location' => 'Not verified',
                'findingType' => 'web_search_result',
                'claim' => 'Search result candidate: '.$title.'. Business identity and fit have not been verified.',
                'excerpt' => $snippet !== '' ? $snippet : 'The search provider returned this result without a descriptive snippet.',
                'dimensions' => [],
                'sourceUrl' => $target['url'],
                'sourceTitle' => $sourceTitle,
                'retrievalMethod' => 'searxng',
                'verificationState' => 'search_result',
                'confidence' => 0.35,
            ];

            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return $results;
    }

    public function sourceMode(): string
    {
        return 'web_search';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
