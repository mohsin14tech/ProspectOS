<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Message\ProcessResearchRun;
use App\MessageHandler\ProcessResearchRunHandler;
use Doctrine\ORM\EntityManagerInterface;

final class DemoWorkspaceSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly ProcessResearchRunHandler $handler,
    ) {
    }

    public function seed(): bool
    {
        $workspace = $this->workspaceProvider->get();
        $existing = $this->entityManager->getRepository(Prospect::class)->findOneBy([
            'workspace' => $workspace,
            'slug' => 'juniper-table',
        ]);
        if ($existing instanceof Prospect) {
            return false;
        }

        $run = new ResearchRun($workspace, 'Synthetic sample records for the local ProspectOS demo.');
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        $this->handler->__invoke(new ProcessResearchRun($run->getId()));

        return true;
    }
}
