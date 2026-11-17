<?php

declare(strict_types=1);

namespace App\Service\Court;

use App\Entity\CourtCase;
use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Zero-key demo: real hearings on the three fictional demo leads, recorded once from a
 * live model, replayed through a Symfony AI Platform when no AI provider is connected.
 *
 * Only the model's words are replayed. The Agent loop, the read-only tools (which really
 * read the database), the clerk and the threading all run for real on every replay.
 * Evidence citations are stored as content fingerprints ({{E:hash}}) so they resolve to
 * the right records on any fresh install. Cases are labelled "Recorded demo".
 */
final class RecordedHearings
{
    public const PROVIDER = 'Recorded demo';

    /** @var array<string, mixed>|null */
    private ?array $recordings = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/config/demo/recorded_hearings.json')]
        private readonly string $file,
    ) {
    }

    public function has(Prospect $prospect): bool
    {
        return 'demo' === $prospect->getWorkspace()->getSlug() && isset($this->all()[$prospect->getSlug()]);
    }

    /** "Groq / openai/gpt-oss-120b, recorded 2026-11-17" */
    public function source(Prospect $prospect): string
    {
        $recording = $this->all()[$prospect->getSlug()] ?? [];

        return ($recording['recorded_with'] ?? 'a live model').', recorded '.($recording['recorded_at'] ?? 'earlier');
    }

    public function platform(Prospect $prospect, int $paceMs = 0): PlatformInterface
    {
        $turns = $this->all()[$prospect->getSlug()]['turns'] ?? throw new \DomainException('No recorded hearing exists for this lead.');
        $ids = $this->evidenceIds($prospect);
        $resolve = static fn (string $text): string => (string) preg_replace_callback('/\{\{E:([0-9a-f]{8})\}\}/', static fn (array $m): string => 'E'.($ids[$m[1]] ?? 0), $text);
        $calls = [];

        return MockPlatformFactory::createPlatform(static function ($model, $input) use ($turns, $resolve, $paceMs, &$calls) {
            if ($paceMs > 0) {
                usleep($paceMs * 1000);
            }
            $prompt = json_encode($input, \JSON_UNESCAPED_UNICODE) ?: '';
            $turn = match (true) {
                str_contains($prompt, 'has replied to your opening') => 'rebuttal',
                str_contains($prompt, 'Reply to the Advocate') => 'skeptic',
                default => 'advocate',
            };
            $recorded = $turns[$turn] ?? null;
            if (null === $recorded) {
                throw new \RuntimeException('This recorded hearing has no '.$turn.' turn.');
            }
            $calls[$turn] = ($calls[$turn] ?? 0) + 1;
            if (1 === $calls[$turn] && [] !== ($recorded['tool_calls'] ?? [])) {
                return new ToolCallResult(array_map(static fn (array $call, int $i): ToolCall => new ToolCall(
                    'rec_'.$turn.'_'.$i,
                    $call['tool'],
                    'read_evidence' === $call['tool'] ? ['evidence_id' => $resolve((string) $call['argument'])] : [],
                ), $recorded['tool_calls'], array_keys($recorded['tool_calls'])));
            }

            return new TextResult($resolve((string) $recorded['raw']));
        });
    }

    /**
     * Turns a finished live hearing on a demo lead into a portable recording.
     *
     * @return array<string, mixed>
     */
    public function capture(CourtCase $case): array
    {
        $prospect = $case->getProspect();
        $transcript = $case->getTranscript();
        $hashes = array_flip($this->evidenceIds($prospect));
        $portable = static fn (string $text): string => (string) preg_replace_callback('/\bE(\d+)\b/', static fn (array $m): string => isset($hashes[(int) $m[1]]) ? '{{E:'.$hashes[(int) $m[1]].'}}' : $m[0], $text);

        $turns = [];
        foreach (['advocate', 'skeptic', 'rebuttal'] as $turn) {
            if (!\is_array($transcript[$turn] ?? null)) {
                continue;
            }
            $turns[$turn] = [
                'tool_calls' => array_values(array_map(static fn (array $call): array => ['tool' => $call['tool'], 'argument' => null === $call['argument'] ? null : $portable($call['argument'])], array_filter($transcript[$turn]['tool_calls'], static fn (array $call): bool => 'team_precedents' !== $call['tool']))),
                'raw' => $portable((string) $transcript[$turn]['raw']),
            ];
        }

        return ['recorded_with' => $case->getProvider().' / '.$case->getModel(), 'recorded_at' => $case->getCreatedAt()->format('Y-m-d'), 'source_case' => $case->getId(), 'turns' => $turns];
    }

    /** @param array<string, array<string, mixed>> $recordings slug => recording */
    public function save(array $recordings): void
    {
        $all = array_replace($this->all(), $recordings);
        ksort($all);
        if (!is_dir(\dirname($this->file))) {
            mkdir(\dirname($this->file), 0o775, true);
        }
        file_put_contents($this->file, json_encode($all, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
        $this->recordings = $all;
    }

    /** @return array<string, int> fingerprint => evidence id, for one lead */
    private function evidenceIds(Prospect $prospect): array
    {
        $ids = [];
        foreach ($this->entityManager->getRepository(EvidenceItem::class)->findBy(['prospect' => $prospect]) as $item) {
            $ids[substr(sha1($item->getFindingType().'|'.$item->getClaim()), 0, 8)] = (int) $item->getId();
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        if (null === $this->recordings) {
            $this->recordings = is_file($this->file) ? (json_decode((string) file_get_contents($this->file), true, 512, \JSON_THROW_ON_ERROR) ?: []) : [];
        }

        return $this->recordings;
    }
}
