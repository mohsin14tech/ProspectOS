<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Twig\MonogramExtension;
use PHPUnit\Framework\TestCase;

final class MonogramTest extends TestCase
{
    public function testMonogramsSkipPunctuationAndLeadingNumbers(): void
    {
        self::assertSame('LA', MonogramExtension::monogram('Loafology | Artisan Bakery & Café'));
        self::assertSame('BC', MonogramExtension::monogram('12 Best Cafes in Islamabad'));
        self::assertSame('TB', MonogramExtension::monogram('THE 10 BEST Cafés in Islamabad'));
        self::assertSame('ÉC', MonogramExtension::monogram('école centrale'));
        self::assertSame('?', MonogramExtension::monogram('2026 | 42'));
    }
}
