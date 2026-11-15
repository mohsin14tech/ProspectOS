<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\DashboardData;
use App\Service\DemoWorkspaceProvider;
use Mcp\Capability\Attribute\McpTool;

final class DemoWorkspaceSummaryTool
{
    public function __construct(private readonly DashboardData $dashboardData, private readonly DemoWorkspaceProvider $workspaceProvider)
    {
    }

    #[McpTool(name: 'demo-workspace-summary')]
    public function summary(): string
    {
        $dashboard = $this->dashboardData->dashboard($this->workspaceProvider->get());

        return json_encode([
            'mode' => 'demo',
            'summary' => $dashboard['summary'],
            'top_prospects' => array_map(
                static fn (array $prospect): array => [
                    'name' => $prospect['name'],
                    'location' => $prospect['location'],
                    'score' => $prospect['score'],
                    'observable_signal' => $prospect['signal'],
                ],
                $dashboard['prospects'],
            ),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
