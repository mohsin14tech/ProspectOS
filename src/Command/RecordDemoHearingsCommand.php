<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Service\Court\EvidenceCourt;
use App\Service\Court\RecordedHearings;
use App\Service\DemoWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Maintainer tool: runs live hearings on the fictional demo leads with the demo workspace's
 * connected AI provider and saves them to config/demo/recorded_hearings.json for the
 * zero-key replay. Team precedents are left out so recordings do not depend on local memory.
 */
#[AsCommand(name: 'app:court:record-demo', description: 'Record live Evidence Court hearings on the demo leads for zero-key replay.')]
final class RecordDemoHearingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly EvidenceCourt $court,
        private readonly RecordedHearings $recordings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slugs', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Demo lead slugs to record (default: all three)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workspace = $this->workspaceProvider->get();
        $saved = [];
        foreach ($input->getArgument('slugs') ?: ['juniper-table', 'northstar-dental', 'olive-and-grain'] as $index => $slug) {
            if ($index > 0) {
                // Free tiers count tokens per minute (Groq: 8,000); let the window reset between hearings.
                $io->writeln('Waiting 65 s for the provider per-minute token window…');
                sleep(65);
            }
            $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy(['workspace' => $workspace, 'slug' => $slug]);
            if (!$prospect instanceof Prospect) {
                $io->warning('Demo lead '.$slug.' is missing. Seed the demo workspace first.');
                continue;
            }
            if ($this->court->isReplay($prospect) || !$this->court->isReady($prospect)) {
                $io->error('Connect and test a real AI provider for the demo workspace first.');

                return Command::FAILURE;
            }
            $case = $this->court->hear($this->court->open($prospect, null, CourtCase::VIA_MCP), usePrecedents: false);
            if (CourtCase::STATUS_ARGUED !== $case->getStatus() || null === ($case->getTranscript()['rebuttal'] ?? null)) {
                $io->warning(\sprintf('%s: case #%d did not produce a full three-turn hearing (%s). Not recorded; run again.', $slug, $case->getId(), $case->getFailure() ?? 'no rebuttal'));
                continue;
            }
            $saved[$slug] = $this->recordings->capture($case);
            $io->writeln(\sprintf('Recorded %s from case #%d.', $slug, $case->getId()));
        }
        if ([] === $saved) {
            return Command::FAILURE;
        }
        $this->recordings->save($saved);
        $io->success('Saved '.\count($saved).' recording(s) to config/demo/recorded_hearings.json.');

        return Command::SUCCESS;
    }
}
