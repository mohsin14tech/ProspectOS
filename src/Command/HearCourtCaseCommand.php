<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Service\Court\EvidenceCourt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:court:hear', description: 'Run an Evidence Court hearing for one prospect and print the clerk-reviewed transcript.')]
final class HearCourtCaseCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EvidenceCourt $court,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Local prospect ID')] int $prospectId, #[\Symfony\Component\Console\Attribute\Option('Replay the recorded demo hearing even if an AI provider is connected (demo leads only)')] bool $replay = false): int
    {
        $prospect = $this->entityManager->getRepository(Prospect::class)->find($prospectId);
        if (!$prospect instanceof Prospect) {
            $io->error('Prospect not found.');

            return Command::FAILURE;
        }
        if (!$this->court->isReady($prospect)) {
            $io->error('No tested AI provider is active for this prospect\'s workspace. Connect one at /integrations/ai.');

            return Command::FAILURE;
        }

        $case = $this->court->hear($this->court->open($prospect, null), forceReplay: $replay);
        if (CourtCase::STATUS_ARGUED !== $case->getStatus()) {
            $io->error((string) $case->getFailure());

            return Command::FAILURE;
        }

        $io->title(sprintf('Evidence Court #%d: %s', $case->getId(), $prospect->getName()));
        foreach (['advocate' => 'Advocate opens', 'skeptic' => 'Skeptic replies', 'rebuttal' => 'Advocate answers back'] as $side => $label) {
            $argument = $case->getTranscript()[$side] ?? null;
            if (null === $argument) {
                continue;
            }
            $io->section(sprintf('%s (tools used: %s)', $label, implode(', ', array_map(static fn (array $call): string => $call['tool'].($call['argument'] ? '('.$call['argument'].')' : ''), $argument['tool_calls'])) ?: 'none'));
            foreach ($argument['claims'] as $claim) {
                $to = isset($claim['reply_to']) ? ('rebuttal' === $side ? '(to S' : '(to A').($claim['reply_to'] + 1).') ' : '';
                $io->writeln(sprintf('  [%s] %s%s %s', strtoupper($claim['ruling']), $to, $claim['text'], $claim['citations'] ? '['.implode(', ', $claim['citations']).']' : ''));
                $io->writeln('      '.$claim['note']);
            }
        }
        $io->success(sprintf('%s / %s in %d ms. A human must rule in the app: /court/%d', $case->getProvider(), $case->getModel(), $case->getLatencyMs(), $case->getId()));

        return Command::SUCCESS;
    }
}
