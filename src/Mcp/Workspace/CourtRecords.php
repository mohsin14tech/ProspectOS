<?php

declare(strict_types=1);

namespace App\Mcp\Workspace;

use App\Service\Court\CourtDocket;
use App\Service\CurrentWorkspaceProvider;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Evidence Court records of the signed-in workspace as MCP resources, plus
 * the cross-examination prompts. Read-only and scoped to the session's workspace.
 */
final class CourtRecords
{
    public function __construct(
        private readonly CourtDocket $docket,
        private readonly CurrentWorkspaceProvider $workspaceProvider,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[McpResource(uri: 'prospectos://court/docket', name: 'court_docket', title: 'Evidence Court docket', description: 'Recent Evidence Court hearings in the signed-in workspace with status and human ruling.', mimeType: CourtDocket::MIME)]
    public function docket(): string
    {
        return $this->json($this->docket->docket($this->workspaceProvider->get(), $this->appUrl()));
    }

    #[McpResourceTemplate(uriTemplate: 'prospectos://court/{caseId}', name: 'court_hearing', title: 'Evidence Court hearing', description: 'One hearing: each side\'s claims with the clerk\'s admitted/struck ruling, the tools counsel called, and the human ruling.', mimeType: CourtDocket::MIME)]
    public function hearing(string $caseId): string
    {
        return $this->json($this->docket->hearing($this->workspaceProvider->get(), (int) $caseId, $this->appUrl()));
    }

    #[McpResourceTemplate(uriTemplate: 'prospectos://lead/{leadId}/evidence', name: 'lead_evidence', title: 'Lead evidence file', description: 'Saved source evidence for one lead in the signed-in workspace, citable as [E<id>].', mimeType: CourtDocket::MIME)]
    public function evidence(string $leadId): string
    {
        return $this->json($this->docket->evidence($this->workspaceProvider->get(), (int) $leadId));
    }

    #[McpPrompt(name: 'cross_examine', title: 'Cross-examine a lead', description: 'Put a lead on trial, check every admitted claim against its evidence, then ask me to rule.')]
    public function crossExamine(string $leadId): array
    {
        return $this->docket->crossExaminePrompt($this->workspaceProvider->get(), (int) $leadId);
    }

    #[McpPrompt(name: 'brief_me', title: 'Brief me on a hearing', description: 'Embed a hearing transcript and get a five-bullet judge\'s brief before ruling.')]
    public function briefMe(string $caseId): array
    {
        return $this->docket->briefPrompt($this->workspaceProvider->get(), (int) $caseId, $this->appUrl());
    }

    private function appUrl(): string
    {
        return rtrim($this->urls->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
