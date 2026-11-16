<?php

declare(strict_types=1);

namespace App\Mcp\Workspace;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Entity\User;
use App\Service\CurrentWorkspaceProvider;
use App\Service\Court\EvidenceCourt;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Lets an authenticated MCP client put a lead on trial and read the
 * clerk-reviewed transcript. There is deliberately no ruling tool:
 * only a person in the ProspectOS UI can decide.
 */
final class EvidenceCourtTool
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentWorkspaceProvider $workspaceProvider,
        private readonly EvidenceCourt $court,
        private readonly Security $security,
    ) {
    }

    #[McpTool(name: 'open_evidence_court', description: 'Put one lead from the signed-in workspace on trial. An AI Advocate and an AI Skeptic argue from saved evidence; a deterministic clerk strikes claims without valid citations. Returns the transcript. This tool cannot rule; a human must rule in the ProspectOS app.')]
    public function open(int $prospectId): string
    {
        $workspace = $this->workspaceProvider->get();
        $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy(['id' => $prospectId, 'workspace' => $workspace]);
        if (!$prospect instanceof Prospect) {
            throw new AccessDeniedException('Prospect is not available in the authenticated workspace.');
        }
        if (!$this->court->isReady($prospect)) {
            throw new \RuntimeException('This workspace has no tested AI connection. A workspace admin must connect one in ProspectOS first.');
        }
        $user = $this->security->getUser();

        $case = $this->court->hear($this->court->open($prospect, $user instanceof User ? $user : null, CourtCase::VIA_MCP));

        return json_encode(EvidenceCourt::describe($case), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    #[McpTool(name: 'get_court_case', description: 'Read an Evidence Court hearing from the signed-in workspace: admitted and struck claims, tools counsel called, and the human ruling if one exists.')]
    public function get(int $caseId): string
    {
        $case = $this->entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $caseId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$case instanceof CourtCase) {
            throw new AccessDeniedException('Court case is not available in the authenticated workspace.');
        }

        return json_encode(EvidenceCourt::describe($case), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
