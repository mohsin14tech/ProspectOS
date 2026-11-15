<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DemoWorkspaceSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:seed', description: 'Create fictional, clearly labelled ProspectOS demo records.')]
final class SeedDemoWorkspaceCommand extends Command
{
    public function __construct(private readonly DemoWorkspaceSeeder $seeder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->seeder->seed()) {
            $io->success('The synthetic demo workspace already has sample prospect records.');

            return Command::SUCCESS;
        }

        $io->success('Created a synthetic demo research run with three fictional prospects, evidence items and deterministic score snapshots.');

        return Command::SUCCESS;
    }
}
