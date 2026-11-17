<?php

declare(strict_types=1);

namespace App\Service\Court;

/**
 * Turns a hearing transcript into an ordered chat thread for people:
 * each Advocate point followed by the Skeptic's replies to it, then the
 * Skeptic's extra concerns, then the Advocate's answers. Clerk rulings are
 * reworded in plain language. Pure presentation: nothing here calls a model.
 */
final class CourtConversation
{
    /**
     * @param array<string, mixed> $transcript
     *
     * @return list<array<string, mixed>>
     */
    public function messages(array $transcript): array
    {
        $advocate = $transcript['advocate']['claims'] ?? [];
        $skeptic = $transcript['skeptic']['claims'] ?? [];
        $rebuttal = $transcript['rebuttal']['claims'] ?? [];
        $threaded = [] !== $skeptic && \array_key_exists('reply_to', $skeptic[0]);

        $messages = [];
        $first = ['advocate' => true, 'skeptic' => true];
        $say = function (string $speaker, array $claim, int $number, ?array $quote = null) use (&$messages, &$first, $transcript): void {
            $side = 'advocate' === $speaker || 'rebuttal' === $speaker ? 'advocate' : 'skeptic';
            $messages[] = [
                'speaker' => $side,
                'number' => $number,
                'label' => ('advocate' === $side ? 'A' : 'S').$number,
                'text' => (string) $claim['text'],
                'ruling' => (string) $claim['ruling'],
                'verdict' => $this->verdict($claim),
                'sources' => $this->sources($claim['citations'] ?? []),
                'read' => (bool) ($claim['read'] ?? false),
                'quote' => $quote,
                // Tool calls are shown once, on the first message each side sends in that turn.
                'checked' => $first[$speaker] ?? false ? $this->checked($transcript[$speaker]['tool_calls'] ?? []) : [],
            ];
            $first[$speaker] = false;
        };
        $quote = static fn (string $who, int $number, array $claim): array => ['who' => $who, 'label' => ('Advocate' === $who ? 'A' : 'S').$number, 'text' => mb_strimwidth((string) $claim['text'], 0, 140, '…')];

        $answered = [];
        foreach ($advocate as $index => $claim) {
            $say('advocate', $claim, $index + 1);
            if ($threaded) {
                foreach ($skeptic as $sIndex => $reply) {
                    if (($reply['reply_to'] ?? null) === $index) {
                        $say('skeptic', $reply, $sIndex + 1, $quote('Advocate', $index + 1, $claim));
                        $answered[$sIndex] = true;
                    }
                }
            }
        }
        foreach ($skeptic as $sIndex => $reply) {
            if (!isset($answered[$sIndex])) {
                $say('skeptic', $reply, $sIndex + 1);
            }
        }
        $first['rebuttal'] = true;
        foreach ($rebuttal as $rIndex => $claim) {
            $target = $claim['reply_to'] ?? null;
            $say('rebuttal', $claim, \count($advocate) + $rIndex + 1, null !== $target && isset($skeptic[$target]) ? $quote('Skeptic', $target + 1, $skeptic[$target]) : null);
        }

        return $messages;
    }

    /**
     * Plain-language bottom line, computed from the clerk's rulings only.
     *
     * @param array<string, mixed> $transcript
     *
     * Deliberately takes no side: a count cannot tell how strong each point is, and a
     * reply can undercut a proven point. The person reads the thread and decides.
     *
     * @return array{for: int, against: int, removed: int, answered: int, headline: string}
     */
    public function bottomLine(array $transcript): array
    {
        $count = static function (?array $side, bool $standing): int {
            $n = 0;
            foreach ($side['claims'] ?? [] as $claim) {
                $n += (CitationClerk::STRUCK !== $claim['ruling']) === $standing ? 1 : 0;
            }

            return $n;
        };
        // Only the opening and the reply are tallied. The Advocate's answers often concede a point,
        // so counting them as "for" would mislead.
        $for = $count($transcript['advocate'] ?? null, true);
        $against = $count($transcript['skeptic'] ?? null, true);
        $removed = $count($transcript['advocate'] ?? null, false) + $count($transcript['skeptic'] ?? null, false) + $count($transcript['rebuttal'] ?? null, false);

        $answered = $count($transcript['rebuttal'] ?? null, true);
        $headline = \sprintf('%d proven %s to pursue, %d proven %s.', $for, 1 === $for ? 'reason' : 'reasons', $against, 1 === $against ? 'worry' : 'worries');

        return ['for' => $for, 'against' => $against, 'removed' => $removed, 'answered' => $answered, 'headline' => $headline];
    }

    /** @param array<string, mixed> $claim */
    private function verdict(array $claim): string
    {
        $note = (string) ($claim['note'] ?? '');

        return match (true) {
            CitationClerk::PRECEDENT === $claim['ruling'] => 'Based on a past team decision. Treat it as a preference, not a fact.',
            CitationClerk::STRUCK !== $claim['ruling'] => 'Checked: backed by the proof shown.',
            str_contains($note, 'repeats') => 'Removed by the clerk: it only repeated the other side.',
            str_contains($note, 'not in this case file') => 'Removed by the clerk: it pointed to proof that does not exist.',
            default => 'Removed by the clerk: no proof was given, so it does not count.',
        };
    }

    /**
     * @param list<string> $citations
     *
     * @return list<array{kind: string, id: int|null, label: string}>
     */
    private function sources(array $citations): array
    {
        $sources = [];
        foreach ($citations as $citation) {
            if ('SCORE' === $citation) {
                $sources[] = ['kind' => 'score', 'id' => null, 'label' => 'Lead score'];
            } elseif (1 === preg_match('/^([EM])(\d+)$/', $citation, $m)) {
                $sources[] = 'E' === $m[1]
                    ? ['kind' => 'evidence', 'id' => (int) $m[2], 'label' => 'Evidence #'.$m[2]]
                    : ['kind' => 'precedent', 'id' => (int) $m[2], 'label' => 'Past decision #'.$m[2]];
            }
        }

        return $sources;
    }

    /**
     * @param list<array{tool: string, argument: string|null}> $calls
     *
     * @return list<string>
     */
    private function checked(array $calls): array
    {
        $checked = [];
        foreach ($calls as $call) {
            $checked[] = match ($call['tool']) {
                'read_evidence' => 'read evidence #'.ltrim((string) $call['argument'], 'E'),
                'score_breakdown' => 'checked the score',
                'team_precedents' => 'looked at past team decisions',
                default => $call['tool'],
            };
        }

        return array_values(array_unique($checked));
    }
}
