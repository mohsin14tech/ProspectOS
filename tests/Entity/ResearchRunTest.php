<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ResearchRun;
use App\Entity\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResearchRun::class)]
final class ResearchRunTest extends TestCase
{
    public function testRunMovesFromQueuedToRunningToCompleted(): void
    {
        $run = new ResearchRun(new Workspace('Demo workspace', 'demo'), 'Find independent cafes in Islamabad.');

        self::assertSame(ResearchRun::STATUS_QUEUED, $run->getStatus());
        $run->start();
        self::assertSame(ResearchRun::STATUS_RUNNING, $run->getStatus());
        self::assertNotNull($run->getStartedAt());

        $run->complete(3);
        self::assertSame(ResearchRun::STATUS_COMPLETED, $run->getStatus());
        self::assertSame('completed', $run->getStage());
        self::assertSame(3, $run->getResultCount());
        self::assertNotNull($run->getCompletedAt());
    }

    public function testFailureMessageDoesNotExposeExceptionDetails(): void
    {
        $run = new ResearchRun(new Workspace('Demo workspace', 'demo'), 'Find independent cafes in Islamabad.');
        $run->fail();

        self::assertSame(ResearchRun::STATUS_FAILED, $run->getStatus());
        self::assertSame('failed', $run->getStage());
        self::assertStringNotContainsString('secret', strtolower($run->getSafeError() ?? ''));
    }
}
