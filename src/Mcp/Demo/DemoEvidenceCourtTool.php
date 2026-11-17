<?php

declare(strict_types=1);

namespace App\Mcp\Demo;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Entity\ScoreSnapshot;
use App\Entity\User;
use App\Entity\WorkspaceMembership;
use App\Service\Court\EvidenceCourt;
use App\Service\Court\McpCourtroom;
use App\Service\DemoLogin;
use App\Service\DemoWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\ClientGateway;

/**
 * STDIO tools for local MCP clients such as Claude Desktop or Claude Code.
 *
 * They only ever touch the fictional demo workspace. The STDIO process runs
 * on the operator's own machine (docker compose exec), so there is no
 * network-facing endpoint here. Rulings stay human-only: request_ruling asks
 * the person through an MCP elicitation form and records only their answer.
 */
final class DemoEvidenceCourtTool
{
    public const APP_URL = 'http://127.0.0.1:8000';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly EvidenceCourt $court,
        private readonly McpCourtroom $courtroom,
    ) {
    }

    #[McpTool(name: 'list_demo_leads', description: 'List the fictional sample leads in the ProspectOS demo workspace with their IDs, deterministic scores and review status.')]
    public function leads(): string
    {
        $workspace = $this->workspaceProvider->get();
        $leads = [];
        foreach ($this->entityManager->getRepository(Prospect::class)->findBy(['workspace' => $workspace], ['id' => 'ASC'], 50) as $prospect) {
            $score = $this->entityManager->getRepository(ScoreSnapshot::class)->findOneBy(['workspace' => $workspace, 'prospect' => $prospect], ['createdAt' => 'DESC']);
            $leads[] = [
                'id' => $prospect->getId(),
                'name' => $prospect->getName(),
                'category' => $prospect->getCategory(),
                'location' => $prospect->getLocation(),
                'status' => $prospect->getStatus(),
                'score' => $score?->getScore(),
            ];
        }

        return json_encode(['workspace' => 'demo (fictional records)', 'leads' => $leads], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    #[McpTool(name: 'open_evidence_court', description: 'Put a demo lead on trial. An AI Advocate and an AI Skeptic (Symfony AI agents with read-only evidence tools) argue; a deterministic clerk strikes claims without valid citations. Streams progress while counsel investigate, then returns the transcript. Cannot rule: use request_ruling to ask the human.')]
    public function open(int $leadId, ?ClientGateway $client = null): string
    {
        $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy(['id' => $leadId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$prospect instanceof Prospect) {
            throw new \InvalidArgumentException('No demo lead has that ID. Call list_demo_leads first.');
        }
        if (!$this->court->isReady($prospect)) {
            throw new \RuntimeException('The demo workspace has no tested AI connection. Connect one at '.self::APP_URL.'/integrations/ai first.');
        }

        $case = $this->courtroom->hear($this->court->open($prospect, null, CourtCase::VIA_MCP), $client);

        return $this->json(EvidenceCourt::describe($case, $this->caseUrl($case)));
    }

    #[McpTool(name: 'request_ruling', description: 'Ask the human user to rule on an argued Evidence Court case. Their MCP client shows a form (Pursue / Reject / Needs more evidence, plus a reason); only their answer is recorded, as team memory. You cannot choose the decision and must never fill in the form for them. Returns the outcome: ruled, declined, cancelled or unsupported.')]
    public function requestRuling(int $caseId, ClientGateway $client): string
    {
        $case = $this->findCase($caseId);

        return $this->json($this->courtroom->requestRuling($case, $this->judge(), $client, $this->caseUrl($case)));
    }

    #[McpTool(name: 'get_court_case', description: 'Read a demo Evidence Court hearing, including struck claims and the human ruling if one exists.')]
    public function get(int $caseId): string
    {
        $case = $this->findCase($caseId);

        return $this->json(EvidenceCourt::describe($case, $this->caseUrl($case)));
    }

    private function findCase(int $caseId): CourtCase
    {
        $case = $this->entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $caseId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$case instanceof CourtCase) {
            throw new \InvalidArgumentException('No demo court case has that ID.');
        }

        return $case;
    }

    private function caseUrl(CourtCase $case): string
    {
        return self::APP_URL.'/court/'.$case->getId();
    }

    /**
     * The STDIO server runs on the operator's own machine, so the person at the
     * MCP client is the local demo operator: the demo workspace owner.
     */
    private function judge(): ?User
    {
        $workspace = $this->workspaceProvider->get();
        $owner = $this->entityManager->getRepository(WorkspaceMembership::class)->findOneBy(['workspace' => $workspace, 'role' => WorkspaceMembership::ROLE_OWNER], ['id' => 'ASC']);
        if ($owner instanceof WorkspaceMembership) {
            return $owner->getUser();
        }
        $demo = $this->entityManager->getRepository(User::class)->findOneBy(['email' => DemoLogin::EMAIL]);

        return $demo instanceof User ? $demo : null;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
