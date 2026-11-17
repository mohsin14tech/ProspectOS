<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Court\CourtConversation;
use PHPUnit\Framework\TestCase;

final class CourtConversationTest extends TestCase
{
    public function testRepliesFollowThePointTheyAnswerAndRebuttalsQuoteTheSkeptic(): void
    {
        $claim = static fn (string $text, string $ruling, ?int $replyTo, array $citations = ['E6']): array => ['text' => $text, 'ruling' => $ruling, 'citations' => $citations, 'note' => 'struck' === $ruling ? 'Struck: no evidence cited.' : 'Admitted: backed by saved evidence.', 'read' => true, 'reply_to' => $replyTo];
        $transcript = [
            'advocate' => ['tool_calls' => [['tool' => 'read_evidence', 'argument' => 'E6'], ['tool' => 'score_breakdown', 'argument' => null]], 'claims' => [
                $claim('Point one.', 'admitted', null),
                $claim('Point two.', 'struck', null, []),
            ]],
            'skeptic' => ['tool_calls' => [], 'claims' => [
                $claim('Extra worry.', 'admitted', null),
                $claim('Answer to two.', 'admitted', 1),
                $claim('Answer to one.', 'admitted', 0),
            ]],
            'rebuttal' => ['tool_calls' => [], 'claims' => [$claim('Fair point, a call settles it.', 'admitted', 2)]],
        ];

        $conversation = new CourtConversation();
        $messages = $conversation->messages($transcript);

        self::assertSame(['A1', 'S3', 'A2', 'S2', 'S1', 'A3'], array_column($messages, 'label'));
        self::assertSame('Advocate', $messages[1]['quote']['who']);
        self::assertSame(['read evidence #6', 'checked the score'], $messages[0]['checked']);
        self::assertSame('Removed by the clerk: no proof was given, so it does not count.', $messages[2]['verdict']);
        self::assertSame('Evidence #6', $messages[0]['sources'][0]['label']);
        self::assertSame(['who' => 'Skeptic', 'label' => 'S3', 'text' => 'Answer to one.'], $messages[5]['quote']);

        $bottom = $conversation->bottomLine($transcript);
        self::assertSame(1, $bottom['for'], 'Struck points and the rebuttal are not counted for pursuing.');
        self::assertSame(3, $bottom['against']);
        self::assertSame(1, $bottom['answered']);
        self::assertSame('1 proven reason to pursue, 3 proven worries.', $bottom['headline']);
    }

    public function testOlderHearingsWithoutReplyLinksStillRenderInOrder(): void
    {
        $plain = static fn (string $text): array => ['text' => $text, 'ruling' => 'admitted', 'citations' => ['SCORE'], 'note' => 'Admitted: backed by the deterministic score.'];
        $messages = (new CourtConversation())->messages([
            'advocate' => ['tool_calls' => [], 'claims' => [$plain('For.')]],
            'skeptic' => ['tool_calls' => [], 'claims' => [$plain('Against.')]],
        ]);

        self::assertSame(['advocate', 'skeptic'], array_column($messages, 'speaker'));
        self::assertNull($messages[1]['quote']);
        self::assertSame('Lead score', $messages[0]['sources'][0]['label']);
    }
}
