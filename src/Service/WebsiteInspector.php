<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebsiteInspector
{
    private const MAX_BYTES = 1_000_000;
    private const MAX_REDIRECTS = 3;
    private const MAX_TOTAL_DURATION = 20.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PublicUrlPolicy $urlPolicy,
        private readonly RobotsTxtFetcher $robotsTxtFetcher,
        private readonly HostAccessGateInterface $hostAccessGate,
    ) {
    }

    /** @return array{url: string, host: string, title: string, description: string, excerpt: string} */
    public function inspect(string $url): array
    {
        $deadline = microtime(true) + self::MAX_TOTAL_DURATION;
        $visited = [];
        for ($redirects = 0; ; ++$redirects) {
            $this->assertTimeRemaining($deadline);
            $target = $this->urlPolicy->validateAndPin($url);
            if (isset($visited[$target['url']])) {
                throw new \RuntimeException('The website returned a redirect loop.');
            }
            $visited[$target['url']] = true;

            $result = $this->hostAccessGate->run($target['host'], function () use ($target, $deadline): array {
                $this->robotsTxtFetcher->assertAllowed($target, $deadline);

                return $this->fetchPage($target, $deadline);
            });

            if (isset($result['redirect'])) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new \RuntimeException('The website exceeded the redirect limit.');
                }
                $url = $result['redirect'];
                continue;
            }

            return $result;
        }
    }

    /** @param array{url: string, host: string, port: int, ip: string} $target
     *  @return array{url: string, host: string, title: string, description: string, excerpt: string}|array{redirect: string}
     */
    private function fetchPage(array $target, float $deadline): array
    {
        $remaining = $this->timeRemaining($deadline);
        $response = $this->httpClient->request('GET', $target['url'], [
            'headers' => ['Accept' => 'text/html,application/xhtml+xml', 'Accept-Encoding' => 'identity', 'User-Agent' => 'ProspectOS-Research/0.1 (+public-page metadata inspection)'],
            'max_redirects' => 0,
            'timeout' => min(5, $remaining),
            'max_duration' => $remaining,
            'buffer' => false,
            'resolve' => [$target['host'] => $target['ip']],
        ]);
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $response->cancel();
            $location = $headers['location'][0] ?? null;
            if (!is_string($location) || $location === '') {
                throw new \RuntimeException('The website returned an invalid redirect.');
            }

            return ['redirect' => $this->resolveRedirect($target['url'], $location)];
        }

        if ($status < 200 || $status >= 300) {
            $response->cancel();
            throw new \RuntimeException('The website did not return a successful page.');
        }

        $contentType = strtolower(trim(explode(';', $headers['content-type'][0] ?? '')[0]));
        if (!in_array($contentType, ['text/html', 'application/xhtml+xml'], true)) {
            $response->cancel();
            throw new \RuntimeException('The website response is not HTML.');
        }
        $contentLength = $headers['content-length'][0] ?? null;
        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > self::MAX_BYTES) {
            $response->cancel();
            throw new \RuntimeException('The website response is too large.');
        }

        $body = '';
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('The website response timed out.');
                }
                $part = $chunk->getContent();
                if (strlen($body) + strlen($part) > self::MAX_BYTES) {
                    $response->cancel();
                    throw new \RuntimeException('The website response is too large.');
                }
                $body .= $part;
            }
        } catch (\Throwable $exception) {
            $response->cancel();
            throw $exception;
        }

        return $this->extractMetadata($target['url'], $target['host'], $body);
    }

    /** @return array{url: string, host: string, title: string, description: string, excerpt: string} */
    private function extractMetadata(string $url, string $host, string $html): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode?->textContent ?? '';
        $description = '';
        foreach ($xpath->query('//meta[@name or @property]') as $meta) {
            $name = strtolower($meta->getAttribute('name') ?: $meta->getAttribute('property'));
            if (in_array($name, ['description', 'og:description'], true) && $meta->hasAttribute('content')) {
                $description = $meta->getAttribute('content');
                break;
            }
        }
        foreach ($xpath->query('//script|//style|//noscript|//template') as $unsafeNode) {
            $unsafeNode->parentNode?->removeChild($unsafeNode);
        }
        $visibleText = trim(preg_replace('/\s+/u', ' ', $document->textContent ?? '') ?? '');
        $title = mb_substr(trim($title), 0, 200);
        $description = mb_substr(trim($description), 0, 500);
        $excerpt = mb_substr($visibleText !== '' ? $visibleText : $description, 0, 500);

        return ['url' => $url, 'host' => $host, 'title' => $title, 'description' => $description, 'excerpt' => $excerpt];
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $location)) {
            throw new \RuntimeException('The website returned an invalid redirect.');
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $base = parse_url($currentUrl);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            throw new \RuntimeException('The website returned an invalid redirect.');
        }
        $origin = $base['scheme'].'://'.(str_contains($base['host'], ':') ? '['.$base['host'].']' : $base['host']).(isset($base['port']) ? ':'.$base['port'] : '');
        if (str_starts_with($location, '//')) {
            return $base['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        if (str_starts_with($location, '?')) {
            return $origin.($base['path'] ?? '/').$location;
        }

        $path = $base['path'] ?? '/';
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);
        return $origin.$directory.$location;
    }

    private function assertTimeRemaining(float $deadline): void
    {
        $this->timeRemaining($deadline);
    }

    private function timeRemaining(float $deadline): float
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw new \RuntimeException('Website inspection exceeded the total time limit.');
        }

        return $remaining;
    }
}
