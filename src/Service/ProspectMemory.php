<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\Workspace;
use App\Service\Memory\PrecedentRetriever;
use Doctrine\ORM\EntityManagerInterface;

/** Small, inspectable workspace memory built from human-reviewed decisions. */
final class ProspectMemory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PrecedentRetriever $retriever = new PrecedentRetriever(),
    ) {
    }

    /** @return list<ProspectDecision> */
    public function relevantDecisions(Workspace $workspace, Prospect $prospect, int $limit = 5): array
    {
        $decisions = $this->entityManager->getRepository(ProspectDecision::class)->findBy(
            ['workspace' => $workspace],
            ['createdAt' => 'DESC'],
            100,
        );

        $relevant = array_values(array_filter($decisions, static function (ProspectDecision $decision) use ($prospect): bool {
            $example = $decision->getProspect();
            if ($example->getId() === $prospect->getId()) {
                return false;
            }

            $categoryIsSpecific = !in_array(mb_strtolower($prospect->getCategory()), ['web search candidate', 'sample', 'website submission'], true)
                && mb_strtolower($example->getCategory()) === mb_strtolower($prospect->getCategory());
            $locationIsSpecific = !in_array(mb_strtolower($prospect->getLocation()), ['unspecified', 'not verified', 'sample'], true)
                && ($example->getLocation() !== 'Unspecified'
                    && !in_array(mb_strtolower($example->getLocation()), ['not verified', 'sample'], true)
                    && mb_strtolower($example->getLocation()) === mb_strtolower($prospect->getLocation()));

            return $categoryIsSpecific || $locationIsSpecific;
        }));

        $limit = max(1, min(20, $limit));
        $relevant = array_slice($relevant, 0, $limit);

        // Then fill with decisions about similar leads, ranked by Symfony AI Store (vector query).
        $taken = array_map(static fn (ProspectDecision $decision): int => (int) $decision->getId(), $relevant);
        $others = array_values(array_filter($decisions, static fn (ProspectDecision $decision): bool => $decision->getProspect()->getId() !== $prospect->getId() && !\in_array((int) $decision->getId(), $taken, true)));
        foreach ($this->retriever->similar($prospect, $others, $limit - \count($relevant)) as $match) {
            $relevant[] = $match['decision'];
        }

        return $relevant;
    }

    /** @return array<string, int> */
    public function counts(Workspace $workspace): array
    {
        $repository = $this->entityManager->getRepository(Prospect::class);

        return [
            Prospect::STATUS_CANDIDATE => $repository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_CANDIDATE]),
            Prospect::STATUS_NEEDS_REVIEW => $repository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_NEEDS_REVIEW]),
            Prospect::STATUS_FINALIZED => $repository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_FINALIZED]),
            Prospect::STATUS_REJECTED => $repository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_REJECTED]),
            Prospect::STATUS_ARCHIVED => $repository->count(['workspace' => $workspace, 'status' => Prospect::STATUS_ARCHIVED]),
        ];
    }
}
