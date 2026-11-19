<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/** Two-letter monogram for a lead name, skipping punctuation and leading numbers ("12 Best Cafes" -> "BC"). */
final class MonogramExtension
{
    #[AsTwigFilter('monogram')]
    public static function monogram(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $name) ?: [],
            static fn (string $word): bool => 1 === preg_match('/^\p{L}/u', $word),
        ));

        return implode('', array_map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2))) ?: '?';
    }
}
