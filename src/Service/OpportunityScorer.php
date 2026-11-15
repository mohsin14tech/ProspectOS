<?php

declare(strict_types=1);

namespace App\Service;

final class OpportunityScorer
{
    private const WEIGHTS = [
        'icpFit' => 25,
        'opportunity' => 30,
        'evidenceQuality' => 20,
        'contactability' => 15,
        'intentRecency' => 10,
    ];

    /**
     * Missing dimensions reduce coverage rather than being treated as zero.
     *
     * @param array<string, int|null> $dimensions
     * @return array{score: ?int, coverage: int, breakdown: array<string, array{value: ?int, weight: int, weightedContribution: ?float}>}
     */
    public function calculate(array $dimensions): array
    {
        $knownWeight = 0;
        $weightedTotal = 0.0;
        $breakdown = [];

        foreach (self::WEIGHTS as $dimension => $weight) {
            $value = $dimensions[$dimension] ?? null;
            if ($value !== null && ($value < 0 || $value > 100)) {
                throw new \InvalidArgumentException(sprintf('Score dimension "%s" must be between 0 and 100.', $dimension));
            }

            $contribution = $value === null ? null : ($value * $weight / 100);
            if ($value !== null) {
                $knownWeight += $weight;
                $weightedTotal += $contribution;
            }

            $breakdown[$dimension] = [
                'value' => $value,
                'weight' => $weight,
                'weightedContribution' => $contribution,
            ];
        }

        return [
            'score' => $knownWeight === 0 ? null : (int) round($weightedTotal * 100 / $knownWeight),
            'coverage' => $knownWeight,
            'breakdown' => $breakdown,
        ];
    }
}
