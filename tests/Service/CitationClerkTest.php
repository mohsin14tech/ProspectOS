<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Court\CitationClerk;
use PHPUnit\Framework\TestCase;

final class CitationClerkTest extends TestCase
{
    public function testAdmitsOnlyClaimsBackedByRecordsInTheCaseFile(): void
    {
        $claims = (new CitationClerk())->review(<<<'TEXT'
- No online ordering was detected on the menu page. [E12]
- The owner is clearly ready to buy.
- A competitor launched ordering last month. [E99]
- The score is driven by observable opportunity. [SCORE]
- The team pursued a similar cafe before. [M4]
TEXT, [12, 14], [4], [12]);

        self::assertCount(5, $claims);
        self::assertSame([CitationClerk::ADMITTED, CitationClerk::STRUCK, CitationClerk::STRUCK, CitationClerk::ADMITTED, CitationClerk::PRECEDENT], array_column($claims, 'ruling'));
        self::assertSame('No online ordering was detected on the menu page.', $claims[0]['text']);
        self::assertTrue($claims[0]['read'], 'E12 was opened with a tool before it was cited.');
        self::assertSame('Struck: no evidence cited.', $claims[1]['note']);
        self::assertStringContainsString('E99', $claims[2]['note']);
        self::assertSame(['SCORE'], $claims[3]['citations']);
        self::assertSame(2, CitationClerk::admittedCount($claims));
    }

    public function testCitationsOutsideBracketsDoNotCount(): void
    {
        $claims = (new CitationClerk())->review('- Record E12 says they have no website.', [12], []);

        self::assertSame(CitationClerk::STRUCK, $claims[0]['ruling']);
    }

    public function testSplitsPlainSentencesAndAcceptsGroupedCitations(): void
    {
        $claims = (new CitationClerk())->review('The menu is a PDF [E3, E5]. They have no booking page [E5].', [3, 5], []);

        self::assertCount(2, $claims);
        self::assertSame(['E3', 'E5'], $claims[0]['citations']);
        self::assertFalse($claims[0]['read']);
        self::assertSame(CitationClerk::ADMITTED, $claims[1]['ruling']);
    }

    public function testCapsTheNumberOfReviewedClaims(): void
    {
        $text = implode("\n", array_fill(0, 20, '- A claim. [E1]'));

        self::assertCount(6, (new CitationClerk())->review($text, [1], []));
    }
}
