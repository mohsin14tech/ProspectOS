<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\ScoreSnapshot;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class DashboardData
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(Workspace $workspace): array
    {
        $prospectRepository = $this->entityManager->getRepository(Prospect::class);
        $prospects = $prospectRepository->findBy(['workspace' => $workspace], ['updatedAt' => 'DESC'], 5);
        $rows = [];
        foreach ($prospects as $prospect) {
            $score = $this->entityManager->getRepository(ScoreSnapshot::class)->findOneBy(
                ['workspace' => $workspace, 'prospect' => $prospect],
                ['createdAt' => 'DESC'],
            );
            $evidence = $this->entityManager->getRepository(\App\Entity\EvidenceItem::class)->findOneBy(
                ['workspace' => $workspace, 'prospect' => $prospect],
                ['retrievedAt' => 'DESC'],
            );
            $words = preg_split('/\s+/', trim($prospect->getName())) ?: [];
            $initials = implode('', array_map(static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2)));

            $rows[] = [
                'id' => $prospect->getId(),
                'name' => $prospect->getName(),
                'category' => $prospect->getCategory(),
                'location' => $prospect->getLocation(),
                'status' => $prospect->getStatus(),
                'score' => $score?->getScore(),
                'signal' => $evidence?->getClaim() ?? 'No saved evidence yet',
                'updated' => $prospect->getUpdatedAt()->format('d M, H:i'),
                'initials' => $initials,
            ];
        }

        $runs = $this->entityManager->getRepository(ResearchRun::class)->findBy(
            ['workspace' => $workspace],
            ['createdAt' => 'DESC'],
            5,
        );
        $activity = array_map(static function (ResearchRun $run): array {
            $resultDescription = match ($run->getSourceMode()) {
                ResearchRun::SOURCE_WEBSITE_INSPECTION => sprintf('%d public page%s inspected', $run->getResultCount(), $run->getResultCount() === 1 ? '' : 's'),
                ResearchRun::SOURCE_WEB_SEARCH => sprintf('%d web candidate%s found', $run->getResultCount(), $run->getResultCount() === 1 ? '' : 's'),
                default => sprintf('%d sample records', $run->getResultCount()),
            };

            return [
                'title' => sprintf('Research run %s', $run->getStatus()),
                'detail' => sprintf('%s · %s', $resultDescription, mb_strimwidth($run->getObjective(), 0, 62, '…')),
                'time' => $run->getCreatedAt()->format('d M, H:i'),
                'kind' => 'research',
            ];
        }, array_slice($runs, 0, 3));

        $locations = array_values(array_unique(array_map(static fn (Prospect $prospect): string => $prospect->getLocation(), $prospects)));

        return [
            'workspace' => $workspace,
            'prospects' => $rows,
            'summary' => [
                'researched' => $prospectRepository->count(['workspace' => $workspace]),
                'finalized' => $prospectRepository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_FINALIZED]),
                'needsReview' => $prospectRepository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_NEEDS_REVIEW]),
            ],
            'locations' => $locations,
            'activity' => $activity,
        ];
    }
}
