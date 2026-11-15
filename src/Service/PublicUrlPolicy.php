<?php

declare(strict_types=1);

namespace App\Service;

final class PublicUrlPolicy
{
    private const GLOBAL_FLAGS = FILTER_FLAG_GLOBAL_RANGE;

    public function __construct(private readonly PublicIpResolverInterface $resolver)
    {
    }

    /** @return array{url: string, host: string, port: int, ip: string} */
    public function validateAndPin(string $url): array
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new \InvalidArgumentException('The website URL is invalid.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('The website URL is invalid.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Only public HTTP or HTTPS website URLs are allowed.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true) || ($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            throw new \InvalidArgumentException('Only standard HTTP and HTTPS ports are allowed.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
            || in_array($host, ['localhost', 'metadata', 'metadata.google.internal', 'metadata.google', 'instance-data.ec2.internal'], true)
            || preg_match('/^[0-9.]+$/', $host)
        ) {
            throw new \InvalidArgumentException('Local and metadata destinations are not allowed.');
        }

        $ipLiteral = trim($host, '[]');
        if (filter_var($ipLiteral, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$ipLiteral];
            $host = $ipLiteral;
        } elseif (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            throw new \InvalidArgumentException('The website hostname is invalid.');
        } else {
            $addresses = $this->resolver->resolve($host);
        }

        if ($addresses === []) {
            throw new \InvalidArgumentException('The website hostname did not resolve to a public address.');
        }

        foreach ($addresses as $address) {
            if (!$this->isGlobalAddress($address)) {
                throw new \InvalidArgumentException('The website hostname resolves to a restricted address.');
            }
        }

        $path = $parts['path'] ?? '/';
        $normalized = $scheme.'://'.(str_contains($host, ':') ? '['.$host.']' : $host).($port === ($scheme === 'https' ? 443 : 80) ? '' : ':'.$port).$path;
        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return ['url' => $normalized, 'host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    private function isGlobalAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // PHP's global-range flag excludes private, reserved, documentation and special-use ranges.
        return filter_var($address, FILTER_VALIDATE_IP, self::GLOBAL_FLAGS) !== false;
    }
}
