<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class PostgreSqlHostAccessGate implements HostAccessGateInterface
{
    private const MINIMUM_INTERVAL_SECONDS = 2;

    private readonly Connection $connection;

    public function __construct(Connection $applicationConnection)
    {
        // Advisory locks and pacing state must survive rollback of the caller's
        // Messenger transaction, so this gate uses its own PostgreSQL session.
        $this->connection = DriverManager::getConnection($applicationConnection->getParams());
    }

    public function run(string $host, callable $operation): mixed
    {
        $locked = $this->connection->fetchOne(
            'SELECT pg_try_advisory_lock(hashtextextended(:host, 809041652))',
            ['host' => strtolower($host)],
        );

        if (!in_array($locked, [true, 1, '1', 't'], true)) {
            throw new \RuntimeException('A page inspection for this host is already in progress. Try again shortly.');
        }

        try {
            $this->connection->executeStatement(
                "DELETE FROM website_host_access_gate WHERE last_seen_at < (clock_timestamp() AT TIME ZONE 'UTC') - INTERVAL '30 days'",
            );
            $acceptedHost = $this->connection->fetchOne(
                "INSERT INTO website_host_access_gate (host, next_allowed_at, last_seen_at)
                 VALUES (:host, (clock_timestamp() AT TIME ZONE 'UTC') + INTERVAL '2 seconds', clock_timestamp() AT TIME ZONE 'UTC')
                 ON CONFLICT (host) DO UPDATE SET
                    next_allowed_at = (clock_timestamp() AT TIME ZONE 'UTC') + INTERVAL '2 seconds',
                    last_seen_at = clock_timestamp() AT TIME ZONE 'UTC'
                 WHERE website_host_access_gate.next_allowed_at <= (clock_timestamp() AT TIME ZONE 'UTC')
                 RETURNING host",
                ['host' => strtolower($host)],
            );

            if (!is_string($acceptedHost)) {
                throw new \RuntimeException('This host was inspected recently. Wait a few seconds before retrying.');
            }

            return $operation();
        } finally {
            $this->connection->fetchOne(
                'SELECT pg_advisory_unlock(hashtextextended(:host, 809041652))',
                ['host' => strtolower($host)],
            );
        }
    }
}
