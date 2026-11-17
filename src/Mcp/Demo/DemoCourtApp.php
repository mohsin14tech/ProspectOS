<?php

declare(strict_types=1);

namespace App\Mcp\Demo;

use App\Service\Court\CourtDocket;
use App\Service\Court\McpCourtroom;
use App\Service\DemoWorkspaceProvider;
use Mcp\Server\ClientGateway;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;

/**
 * MCP App: an interactive Evidence Court card that hosts supporting MCP Apps
 * (for example Claude) render inline. Admitted claims are highlighted, struck
 * claims are crossed out with the clerk's reason, and "Rule on this case"
 * opens the human-only elicitation form.
 */
#[AsMcpApp(
    uri: 'ui://evidence-court/hearing',
    name: 'show_court_case',
    title: 'Evidence Court hearing',
    description: 'Show an Evidence Court hearing from the demo workspace as an interactive card: admitted and struck claims side by side, the tools each counsel called, and the human ruling. Hosts without MCP Apps receive the same data as JSON.',
    template: 'mcp/court_app.html.twig',
    method: 'show',
    toolTemplate: 'mcp/_court_card.html.twig',
    prefersBorder: true,
)]
final class DemoCourtApp
{
    public function __construct(
        private readonly CourtDocket $docket,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly DemoEvidenceCourtTool $court,
    ) {
    }

    /** @return array<string, mixed> */
    public function show(int $caseId): array
    {
        return $this->docket->card($this->workspaceProvider->get(), $caseId, DemoEvidenceCourtTool::APP_URL);
    }

    /** @return array<string, mixed> */
    #[AsMcpAppTool(name: 'refresh_court_card', description: 'Re-render the Evidence Court card.', template: 'mcp/_court_card.html.twig', appOnly: true)]
    public function refresh(string $case_id): array
    {
        return $this->show((int) $case_id);
    }

    /** @return array<string, mixed> */
    #[AsMcpAppTool(name: 'rule_from_card', description: 'Opens the human ruling form for the case shown on the card.', template: 'mcp/_court_card.html.twig', appOnly: true)]
    public function rule(string $case_id, ClientGateway $client): array
    {
        $result = json_decode($this->court->requestRuling((int) $case_id, $client), true, 512, \JSON_THROW_ON_ERROR);

        return $this->docket->card($this->workspaceProvider->get(), (int) $case_id, DemoEvidenceCourtTool::APP_URL, McpCourtroom::noticeFor($result));
    }
}
