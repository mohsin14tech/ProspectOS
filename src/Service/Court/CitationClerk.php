<?php

declare(strict_types=1);

namespace App\Service\Court;

/**
 * Deterministic referee for model-written arguments.
 *
 * Splits an argument into claims and admits only claims that cite evidence
 * records actually present in the case file. Uncited or wrongly cited claims
 * are struck. No model is involved, so a hallucination cannot talk its way in.
 */
final class CitationClerk
{
    public const ADMITTED = 'admitted';
    public const PRECEDENT = 'precedent';
    public const STRUCK = 'struck';

    private const MAX_CLAIMS = 6;
    private const MAX_CLAIM_LENGTH = 400;

    /**
     * @param list<int> $evidenceIds  evidence IDs in this case file
     * @param list<int> $precedentIds team decision IDs counsel may cite as precedent
     * @param list<int> $readIds      evidence IDs this counsel actually opened with a tool
     *
     * @return list<array{text: string, citations: list<string>, ruling: string, note: string, read: bool}>
     */
    public function review(string $argument, array $evidenceIds, array $precedentIds, array $readIds = []): array
    {
        $claims = [];
        foreach ($this->split($argument) as $text) {
            preg_match_all('/\b([EM])\s?(\d+)\b/', $this->citationText($text), $matches, PREG_SET_ORDER);
            $evidence = [];
            $precedent = [];
            $unknown = [];
            foreach ($matches as [, $kind, $number]) {
                $id = (int) $number;
                if ('E' === $kind) {
                    in_array($id, $evidenceIds, true) ? $evidence[$id] = true : $unknown[] = 'E'.$id;
                } else {
                    in_array($id, $precedentIds, true) ? $precedent[$id] = true : $unknown[] = 'M'.$id;
                }
            }

            $score = 1 === preg_match('/\bSCORE\b/i', $this->citationText($text));
            $citations = [...($score ? ['SCORE'] : []), ...array_map(static fn (int $id): string => 'E'.$id, array_keys($evidence)), ...array_map(static fn (int $id): string => 'M'.$id, array_keys($precedent)), ...$unknown];
            $clean = trim((string) preg_replace('/\s*\[[^\]]*\]/', '', $text));
            if ('' === $clean) {
                continue;
            }

            if ([] !== $unknown) {
                $ruling = self::STRUCK;
                $note = 'Struck: cites '.implode(', ', array_unique($unknown)).', which is not in this case file.';
            } elseif ([] !== $evidence || $score) {
                $ruling = self::ADMITTED;
                $note = [] !== $evidence ? 'Admitted: backed by saved evidence.' : 'Admitted: backed by the deterministic score.';
            } elseif ([] !== $precedent) {
                $ruling = self::PRECEDENT;
                $note = 'Admitted as team preference only, not as a fact about this lead.';
            } else {
                $ruling = self::STRUCK;
                $note = 'Struck: no evidence cited.';
            }

            $claims[] = [
                'text' => mb_substr($clean, 0, self::MAX_CLAIM_LENGTH),
                'citations' => $citations,
                'ruling' => $ruling,
                'note' => $note,
                'read' => [] !== $evidence && [] === array_diff(array_keys($evidence), $readIds),
            ];

            if (\count($claims) >= self::MAX_CLAIMS) {
                break;
            }
        }

        return $claims;
    }

    /** @param list<array{ruling: string}> $claims */
    public static function admittedCount(array $claims): int
    {
        return \count(array_filter($claims, static fn (array $claim): bool => self::ADMITTED === $claim['ruling']));
    }

    /** @return list<string> */
    private function split(string $argument): array
    {
        $argument = str_replace("\r", '', $argument);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $argument)), static fn (string $line): bool => '' !== $line));
        $bullets = array_values(array_filter($lines, static fn (string $line): bool => 1 === preg_match('/^(?:[-*•]|\d+[.)])\s+/u', $line)));
        if ([] !== $bullets) {
            return array_map(static fn (string $line): string => (string) preg_replace('/^(?:[-*•]|\d+[.)])\s+/u', '', $line), $bullets);
        }

        $sentences = preg_split('/(?<=[.!?]|\])\s+(?=[A-Z])/u', implode(' ', $lines)) ?: [];

        return array_values(array_filter(array_map('trim', $sentences), static fn (string $s): bool => mb_strlen($s) > 3));
    }

    /** Only text inside square brackets counts as a citation. */
    private function citationText(string $claim): string
    {
        preg_match_all('/\[([^\]]{1,60})\]/', $claim, $matches);

        return implode(' ', $matches[1]);
    }
}
