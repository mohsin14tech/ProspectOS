<?php

declare(strict_types=1);

namespace App\Mcp\Workspace;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Entity\User;
use App\Service\CurrentWorkspaceProvider;
use App\Service\Court\EvidenceCourt;
use App\Service\Court\McpCourtroom;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\ClientGateway;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Lets an authenticated MCP client put a lead on trial and read the
 * clerk-reviewed transcript. A model can never rule: request_ruling has no
 * decision argument and records only what the signed-in person answers in
 * the elicitation form their MCP client shows.
 */
final class EvidenceCourtTool
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentWorkspaceProvider $workspaceProvider,
        private readonly EvidenceCourt $court,
        private readonly Security $security,
        private readonly McpCourtroom $courtroom,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[McpTool(name: 'open_evidence_court', description: 'Put one lead from the signed-in workspace on trial. An AI Advocate and an AI Skeptic argue from saved evidence; a deterministic clerk strikes claims without valid citations. Streams progress while counsel investigate, then returns the transcript. This tool cannot rule: use request_ruling to ask the human.')]
    public function open(int $prospectId, ?ClientGateway $client = null): string
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

        $case = $this->courtroom->hear($this->court->open($prospect, $user instanceof User ? $user : null, CourtCase::VIA_MCP), $client);

        return json_encode(EvidenceCourt::describe($case, $this->caseUrl($case)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    #[McpTool(name: 'request_ruling', description: 'Ask the signed-in user to rule on an argued Evidence Court case. Their MCP client shows a form (Pursue / Reject / Needs more evidence, plus a reason); only their answer is recorded, as team memory. You cannot choose the decision and must never fill in the form for them. Returns the outcome: ruled, declined, cancelled or unsupported.')]
    public function requestRuling(int $caseId, ClientGateway $client): string
    {
        $case = $this->findCase($caseId);
        $user = $this->security->getUser();

        return json_encode($this->courtroom->requestRuling($case, $user instanceof User ? $user : null, $client, $this->caseUrl($case)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    #[McpTool(name: 'get_court_case', description: 'Read an Evidence Court hearing from the signed-in workspace: admitted and struck claims, tools counsel called, and the human ruling if one exists.')]
    public function get(int $caseId): string
    {
        $case = $this->findCase($caseId);

        return json_encode(EvidenceCourt::describe($case, $this->caseUrl($case)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function findCase(int $caseId): CourtCase
    {
        $case = $this->entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $caseId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$case instanceof CourtCase) {
            throw new AccessDeniedException('Court case is not available in the authenticated workspace.');
        }

        return $case;
    }

    private function caseUrl(CourtCase $case): string
    {
        return $this->urls->generate('app_court_show', ['id' => $case->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
