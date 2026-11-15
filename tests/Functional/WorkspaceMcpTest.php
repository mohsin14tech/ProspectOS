<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceMcpTest extends WebTestCase
{
    public function testWorkspaceMcpEndpointRequiresLoginAndReturnsScopedEvidence(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mcp/workspace');
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('MCP Test Workspace', 'mcp-test-workspace');
        $user = new User('mcp-owner@example.test');
        $user->setPassword('unused-test-password');
        $run = new ResearchRun($workspace, 'Find public restaurant candidates', null, ResearchRun::SOURCE_WEB_SEARCH);
        $prospect = new Prospect($workspace, $run, 'mcp-candidate', 'MCP Candidate', 'Web search candidate', 'Not verified');
        $evidence = new EvidenceItem($workspace, $prospect, $run, 'web_search', 'Search candidate, not verified.', 'Candidate source', 'A public search snippet.');
        $evidence->markSearchResult('https://candidate.example/', 'A public search snippet.', 0.35);
        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);
        $entityManager->persist($evidence);
        $foreignWorkspace = new Workspace('Foreign workspace', 'foreign-mcp-workspace');
        $foreignRun = new ResearchRun($foreignWorkspace, 'Private research', null, ResearchRun::SOURCE_DEMO);
        $foreignProspect = new Prospect($foreignWorkspace, $foreignRun, 'private-mcp-candidate', 'Private MCP Prospect', 'Sample', 'Sample');
        $entityManager->persist($foreignWorkspace);
        $entityManager->persist($foreignRun);
        $entityManager->persist($foreignProspect);
        $entityManager->flush();
        $prospectId = $prospect->getId();
        $foreignProspectId = $foreignProspect->getId();

        $client->loginUser($user);
        $client->request('POST', '/mcp/workspace', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'ProspectOS test', 'version' => '1.0'],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $sessionId = $client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotEmpty($sessionId);

        $client->request('POST', '/mcp/workspace', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $sessionId,
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'get_workspace_summary', 'arguments' => new \stdClass()],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $summaryResponse = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $summary = json_decode($summaryResponse['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('MCP Test Workspace', $summary['workspace']);
        self::assertCount(1, $summary['candidates']);

        $client->request('POST', '/mcp/workspace', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $sessionId,
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'get_prospect_report', 'arguments' => ['prospectId' => $prospectId]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $report = json_decode($response['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('MCP Candidate', $report['prospect']['name']);
        self::assertSame('search_result', $report['evidence'][0]['verification_state']);
        self::assertSame('https://candidate.example/', $report['evidence'][0]['source_url']);

        $client->request('POST', '/mcp/workspace', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $sessionId,
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'get_prospect_report', 'arguments' => ['prospectId' => $foreignProspectId]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $denied = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(-32603, $denied['error']['code']);
        self::assertStringNotContainsString('Private MCP Prospect', $client->getResponse()->getContent());
    }
}
