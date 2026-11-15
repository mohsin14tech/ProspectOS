<?php

declare(strict_types=1);

namespace App\Mcp\Workspace;

use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ScoreSnapshot;
use App\Service\CurrentWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProspectReportTool
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentWorkspaceProvider $workspaceProvider,
    ) {
    }

    #[McpTool(name: 'get_prospect_report', description: 'Read a prospect, saved source evidence and deterministic score from the authenticated user’s current workspace. The prospect ID is a local numeric record ID.')]
    public function getProspectReport(int $prospectId): string
    {
        $workspace = $this->workspaceProvider->get();
        $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy([
            'id' => $prospectId,
            'workspace' => $workspace,
        ]);

        if (!$prospect instanceof Prospect) {
            throw new AccessDeniedException('Prospect is not available in the authenticated workspace.');
        }

        $evidence = $this->entityManager->getRepository(EvidenceItem::class)->findBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['retrievedAt' => 'DESC'],
            50,
        );
        $score = $this->entityManager->getRepository(ScoreSnapshot::class)->findOneBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['createdAt' => 'DESC'],
        );

        return json_encode([
            'prospect' => [
                'id' => $prospect->getId(),
                'name' => $prospect->getName(),
                'category' => $prospect->getCategory(),
                'location' => $prospect->getLocation(),
                'source_mode' => $prospect->getSourceMode(),
                'research_objective' => $prospect->getResearchRun()->getObjective(),
            ],
            'score' => $score === null ? null : [
                'value' => $score->getScore(),
                'coverage' => $score->getCoverage(),
                'breakdown' => $score->getBreakdown(),
            ],
            'evidence' => array_map(static fn (EvidenceItem $item): array => [
                'finding_type' => $item->getFindingType(),
                'claim' => $item->getClaim(),
                'source_title' => $item->getSourceTitle(),
                'source_url' => $item->getSourceUrl(),
                'excerpt' => $item->getSupportingExcerpt(),
                'retrieval_method' => $item->getRetrievalMethod(),
                'verification_state' => $item->getVerificationState(),
                'confidence' => $item->getConfidence(),
                'retrieved_at' => $item->getRetrievedAt()->format(DATE_ATOM),
            ], $evidence),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
