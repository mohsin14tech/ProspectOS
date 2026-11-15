<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\OpportunityScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpportunityScorer::class)]
final class OpportunityScorerTest extends TestCase
{
    public function testKnownDimensionsAreRenormalizedAndCoverageIsExplicit(): void
    {
        $result = (new OpportunityScorer())->calculate([
            'icpFit' => 82,
            'opportunity' => 76,
            'evidenceQuality' => 55,
            'contactability' => null,
            'intentRecency' => null,
        ]);

        self::assertSame(72, $result['score']);
        self::assertSame(75, $result['coverage']);
        self::assertNull($result['breakdown']['contactability']['value']);
    }

    public function testAllUnknownDimensionsDoNotBecomeAZeroScore(): void
    {
        $result = (new OpportunityScorer())->calculate([]);

        self::assertNull($result['score']);
        self::assertSame(0, $result['coverage']);
    }

    public function testDimensionOutsideTheScoreRangeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new OpportunityScorer())->calculate(['icpFit' => 101]);
    }
}
