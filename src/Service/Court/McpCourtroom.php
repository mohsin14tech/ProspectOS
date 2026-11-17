<?php

declare(strict_types=1);

namespace App\Service\Court;

use App\Entity\CourtCase;
use App\Entity\ProspectDecision;
use App\Entity\User;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Elicitation\EnumSchemaDefinition;
use Mcp\Schema\Elicitation\StringSchemaDefinition;
use Mcp\Server\ClientGateway;
use Psr\Log\LoggerInterface;

/**
 * The MCP side of Evidence Court, shared by the STDIO demo server and the
 * authenticated workspace server.
 *
 *  - hear(): runs a hearing and streams each step to the client as MCP
 *    progress notifications (no-op when the client sent no progress token).
 *  - requestRuling(): asks the human, through an MCP elicitation form shown by
 *    their client, for a ruling. The decision only ever comes from the form the
 *    person fills in; the tool takes no decision argument, so a model cannot
 *    rule on its own.
 */
final class McpCourtroom
{
    public const DECISIONS = [
        ProspectDecision::DECISION_FINALIZED => 'Pursue',
        ProspectDecision::DECISION_REJECTED => 'Reject',
        ProspectDecision::DECISION_NEEDS_REVIEW => 'Needs more evidence',
    ];

    /** Seconds a person gets to fill in the ruling form. */
    private const RULING_TIMEOUT = 600;

    public function __construct(
        private readonly EvidenceCourt $court,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function hear(CourtCase $case, ?ClientGateway $client): CourtCase
    {
        return $this->court->hear($case, onProgress: null === $client ? null : function (float $step, string $message) use ($client): void {
            try {
                $client->progress($step, EvidenceCourt::PROGRESS_STEPS, $message);
            } catch (\Throwable $exception) {
                // Progress is a courtesy; a client that cannot take it must not break the hearing.
                $this->logger->debug('MCP progress notification was not delivered.', ['message' => $exception->getMessage()]);
            }
        });
    }

    /**
     * @return array<string, mixed> a model-friendly result
     */
    public function requestRuling(CourtCase $case, ?User $judge, ClientGateway $client, string $caseUrl): array
    {
        if (CourtCase::STATUS_RULED === $case->getStatus()) {
            return ['outcome' => 'already_ruled', 'message' => 'A human already ruled on this case.'] + EvidenceCourt::describe($case, $caseUrl);
        }
        if (CourtCase::STATUS_ARGUED !== $case->getStatus()) {
            return ['outcome' => 'not_ready', 'message' => 'Only an argued case can be ruled on. Its status is "'.$case->getStatus().'".', 'case_url' => $caseUrl];
        }
        if (!$judge instanceof User) {
            return ['outcome' => 'no_judge', 'message' => 'No workspace member is available to record the ruling against. Rule in the app instead.', 'case_url' => $caseUrl];
        }
        if (!$client->supportsElicitation()) {
            return ['outcome' => 'unsupported', 'message' => 'This MCP client cannot show forms (it did not declare the elicitation capability). Ask the user to rule at '.$caseUrl.'.', 'case_url' => $caseUrl];
        }

        $result = $client->elicit($this->summary($case, $caseUrl), new ElicitationSchema([
            'decision' => new EnumSchemaDefinition(
                'Your ruling',
                array_keys(self::DECISIONS),
                'Recorded as a team decision and reused by future hearings as a precedent.',
                null,
                array_values(self::DECISIONS),
            ),
            'reason' => new StringSchemaDefinition(
                'Reason',
                'One or two sentences. Cite the admitted claims you relied on.',
                minLength: 3,
                maxLength: 960,
            ),
        ], ['decision', 'reason']), self::RULING_TIMEOUT);

        if (!$result->isAccepted()) {
            return [
                'outcome' => $result->isDeclined() ? 'declined' : 'cancelled',
                'message' => 'The user did not rule. Nothing was recorded. Do not retry unless they ask.',
                'case_url' => $caseUrl,
            ];
        }

        $decision = (string) ($result->content['decision'] ?? '');
        $reason = trim((string) ($result->content['reason'] ?? ''));
        if (!isset(self::DECISIONS[$decision])) {
            return ['outcome' => 'invalid', 'message' => 'The form came back without a valid decision. Nothing was recorded.', 'case_url' => $caseUrl];
        }

        try {
            $this->court->rule($case, $judge, $decision, $reason, CourtCase::VIA_MCP);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            return ['outcome' => 'invalid', 'message' => $exception->getMessage().' Nothing was recorded.', 'case_url' => $caseUrl];
        }

        return ['outcome' => 'ruled', 'message' => 'The user ruled: '.self::DECISIONS[$decision].'. It is now team memory.'] + EvidenceCourt::describe($case, $caseUrl);
    }

    /**
     * The requestRuling() outcome, worded for the person looking at the MCP App card.
     *
     * @param array<string, mixed> $result
     */
    public static function noticeFor(array $result): string
    {
        return match ($result['outcome'] ?? null) {
            'ruled' => 'Your ruling is recorded as team memory.',
            'declined', 'cancelled' => 'You closed the form without ruling. Nothing was recorded.',
            'unsupported' => 'This app cannot show the ruling form. Use "Open in ProspectOS" to rule there.',
            default => (string) ($result['message'] ?? 'Nothing was recorded.'),
        };
    }

    private function summary(CourtCase $case, string $caseUrl): string
    {
        $transcript = $case->getTranscript();
        $lines = ['Evidence Court #'.$case->getId().': should your team pursue '.$case->getProspect()->getName().'?', ''];
        foreach (['advocate' => 'Advocate opens (pursue)', 'skeptic' => 'Skeptic replies (not yet)', 'rebuttal' => 'Advocate answers back'] as $side => $label) {
            if (null === ($transcript[$side] ?? null)) {
                continue;
            }
            $lines[] = $label.':';
            foreach ($transcript[$side]['claims'] ?? [] as $claim) {
                $to = isset($claim['reply_to']) ? ('rebuttal' === $side ? '(to S' : '(to A').($claim['reply_to'] + 1).') ' : '';
                $lines[] = (CitationClerk::STRUCK === $claim['ruling'] ? '  [struck] ' : '  [admitted] ').$to.mb_substr((string) $claim['text'], 0, 220);
            }
        }
        $lines[] = '';
        $lines[] = 'Struck claims had no valid citation; ignore them. Full transcript: '.$caseUrl;

        return implode("\n", $lines);
    }
}
