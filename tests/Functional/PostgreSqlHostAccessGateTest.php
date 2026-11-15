<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\PostgreSqlHostAccessGate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostgreSqlHostAccessGateTest extends KernelTestCase
{
    public function testAdvisoryLockAndPersistentPacingAreSharedAcrossConnections(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $host = 'gate-'.bin2hex(random_bytes(8)).'.example.test';
        $gate = static::getContainer()->get(PostgreSqlHostAccessGate::class);
        $otherGate = new PostgreSqlHostAccessGate($connection);

        self::assertSame('entered', $gate->run($host, static function () use ($otherGate, $host): string {
            try {
                $otherGate->run($host, static fn (): string => 'unexpected');
                self::fail('A second database session must not enter the same host gate concurrently.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('already in progress', $exception->getMessage());
            }

            return 'entered';
        }));

        try {
            $gate->run($host, static fn (): string => 'unexpected');
            self::fail('A second host inspection within the pacing interval must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('inspected recently', $exception->getMessage());
        }
    }
}
