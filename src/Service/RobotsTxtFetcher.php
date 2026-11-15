<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RobotsTxtFetcher
{
    private const MAX_BYTES = 512_000;
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PublicUrlPolicy $urlPolicy,
        private readonly HostAccessGateInterface $hostAccessGate,
        private readonly RobotsTxtPolicy $robotsPolicy,
    ) {
    }

    /** @param array{url: string, host: string, port: int, ip: string} $pageTarget */
    public function assertAllowed(array $pageTarget, float $deadline): void
    {
        $parts = parse_url($pageTarget['url']);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('The website URL is invalid.');
        }
        $origin = $parts['scheme'].'://'.(str_contains($parts['host'], ':') ? '['.$parts['host'].']' : $parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }
        $robots = $this->fetch($origin.'/robots.txt', $pageTarget['host'], 0, $deadline);

        if (!$this->robotsPolicy->allows($robots, $pageTarget['url'])) {
            throw new \RuntimeException('This page is disallowed for ProspectOS-Research by the site robots.txt rules.');
        }
    }

    private function fetch(string $url, string $originHost, int $redirects, float $deadline): string
    {
        $target = $this->urlPolicy->validateAndPin($url);
        $operation = fn (): array => $this->request($target, $deadline);
        $result = $target['host'] === $originHost
            ? $operation()
            : $this->hostAccessGate->run($target['host'], $operation);

        if ($result['status'] >= 300 && $result['status'] < 400) {
            if ($redirects >= self::MAX_REDIRECTS) {
                throw new \RuntimeException('The site robots.txt redirect limit was exceeded. Page access was blocked.');
            }
            $location = $result['location'];
            if (!is_string($location) || $location === '') {
                throw new \RuntimeException('The site robots.txt redirect was invalid. Page access was blocked.');
            }

            return $this->fetch($this->resolveRedirect($target['url'], $location), $originHost, $redirects + 1, $deadline);
        }

        if ($result['status'] >= 400 && $result['status'] < 500) {
            if (in_array($result['status'], [401, 403, 429], true)) {
                throw new \RuntimeException(sprintf('The site robots.txt returned HTTP %d. Page access was blocked.', $result['status']));
            }

            // RFC 9309 defines a 4xx robots.txt response as unavailable.
            return '';
        }

        if ($result['status'] < 200 || $result['status'] >= 300 || $result['body'] === null) {
            throw new \RuntimeException('The site robots.txt could not be fetched. Page access was blocked.');
        }
        if (!mb_check_encoding($result['body'], 'UTF-8')) {
            throw new \RuntimeException('The site robots.txt was not valid UTF-8. Page access was blocked.');
        }

        return $result['body'];
    }

    /** @param array{url: string, host: string, port: int, ip: string} $target
     *  @return array{status: int, location: ?string, body: ?string}
     */
    private function request(array $target, float $deadline): array
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw new \RuntimeException('Website inspection exceeded the total time limit. Page access was blocked.');
        }
        $response = $this->httpClient->request('GET', $target['url'], [
            'headers' => [
                'Accept' => 'text/plain',
                'Accept-Encoding' => 'identity',
                'User-Agent' => 'ProspectOS-Research/0.1 (+public-page metadata inspection)',
            ],
            'max_redirects' => 0,
            'timeout' => min(5, $remaining),
            'max_duration' => $remaining,
            'buffer' => false,
            'resolve' => [$target['host'] => $target['ip']],
        ]);
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);

        if ($status >= 300 && $status < 400) {
            $location = $headers['location'][0] ?? null;
            $response->cancel();

            return ['status' => $status, 'location' => is_string($location) ? $location : null, 'body' => null];
        }

        if ($status >= 400 && $status < 500) {
            $response->cancel();

            return ['status' => $status, 'location' => null, 'body' => null];
        }

        if ($status < 200 || $status >= 300) {
            $response->cancel();

            return ['status' => $status, 'location' => null, 'body' => null];
        }

        $contentType = strtolower(trim(explode(';', $headers['content-type'][0] ?? '')[0]));
        if ($contentType !== 'text/plain') {
            $response->cancel();
            throw new \RuntimeException('The site robots.txt response was not plain text. Page access was blocked.');
        }
        $contentLength = $headers['content-length'][0] ?? null;
        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > self::MAX_BYTES) {
            $response->cancel();
            throw new \RuntimeException('The site robots.txt is too large. Page access was blocked.');
        }

        $body = '';
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('The site robots.txt request timed out. Page access was blocked.');
                }
                $part = $chunk->getContent();
                if (strlen($body) + strlen($part) > self::MAX_BYTES) {
                    throw new \RuntimeException('The site robots.txt is too large. Page access was blocked.');
                }
                $body .= $part;
            }
        } catch (\Throwable $exception) {
            $response->cancel();
            throw $exception;
        }

        return ['status' => $status, 'location' => null, 'body' => $body];
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $location)) {
            throw new \RuntimeException('The site robots.txt redirect was invalid. Page access was blocked.');
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $base = parse_url($currentUrl);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            throw new \RuntimeException('The site robots.txt redirect was invalid. Page access was blocked.');
        }
        $authority = str_contains($base['host'], ':') ? '['.$base['host'].']' : $base['host'];
        $origin = $base['scheme'].'://'.$authority.(isset($base['port']) ? ':'.$base['port'] : '');
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
}
