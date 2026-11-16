<?php

declare(strict_types=1);

namespace App\Mcp\Workspace;

use App\Service\CurrentWorkspaceProvider;
use App\Service\DashboardData;
use Mcp\Capability\Attribute\McpTool;

final class WorkspaceSummaryTool
{
    public function __construct(
        private readonly CurrentWorkspaceProvider $workspaceProvider,
        private readonly DashboardData $dashboardData,
    ) {
    }

    #[McpTool(name: 'get_workspace_summary', description: 'Read a summary and recent candidates for the authenticated user’s current ProspectOS workspace.')]
    public function getWorkspaceSummary(): string
    {
        $dashboard = $this->dashboardData->dashboard($this->workspaceProvider->get());

        return json_encode([
            'workspace' => $dashboard['workspace']->getName(),
            'summary' => $dashboard['summary'],
            'candidates' => array_map(static fn (array $prospect): array => [
                'id' => $prospect['id'],
                'name' => $prospect['name'],
                'category' => $prospect['category'],
                'location' => $prospect['location'],
                'status' => $prospect['status'],
                'score' => $prospect['score'],
                'latest_evidence' => $prospect['signal'],
            ], $dashboard['prospects']),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
