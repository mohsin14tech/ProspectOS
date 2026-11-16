<?php

declare(strict_types=1);

namespace App\Mcp\Demo;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Entity\ScoreSnapshot;
use App\Service\Court\EvidenceCourt;
use App\Service\DemoWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;

/**
 * STDIO tools for local MCP clients such as Claude Desktop or Claude Code.
 *
 * They only ever touch the fictional demo workspace. The STDIO process runs
 * on the operator's own machine (docker compose exec), so there is no
 * network-facing endpoint here. Rulings remain a human-only action in the UI.
 */
final class DemoEvidenceCourtTool
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly EvidenceCourt $court,
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

    #[McpTool(name: 'open_evidence_court', description: 'Put a demo lead on trial. An AI Advocate and an AI Skeptic (Symfony AI agents with read-only evidence tools) argue; a deterministic clerk strikes claims without valid citations. Returns the transcript. Cannot rule: a human must rule in the ProspectOS app.')]
    public function open(int $leadId): string
    {
        $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy(['id' => $leadId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$prospect instanceof Prospect) {
            throw new \InvalidArgumentException('No demo lead has that ID. Call list_demo_leads first.');
        }
        if (!$this->court->isReady($prospect)) {
            throw new \RuntimeException('The demo workspace has no tested AI connection. Connect one at http://127.0.0.1:8000/integrations/ai first.');
        }

        $case = $this->court->hear($this->court->open($prospect, null, CourtCase::VIA_MCP));

        return json_encode(EvidenceCourt::describe($case, 'http://127.0.0.1:8000/court/'.$case->getId()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    #[McpTool(name: 'get_court_case', description: 'Read a demo Evidence Court hearing, including struck claims and the human ruling if one exists.')]
    public function get(int $caseId): string
    {
        $case = $this->entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $caseId, 'workspace' => $this->workspaceProvider->get()]);
        if (!$case instanceof CourtCase) {
            throw new \InvalidArgumentException('No demo court case has that ID.');
        }

        return json_encode(EvidenceCourt::describe($case, 'http://127.0.0.1:8000/court/'.$case->getId()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
