<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\CourtCase;
use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\ResearchRun;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Client;
use Mcp\Client\Builder;
use Mcp\Client\Handler\Request\ElicitationCallbackInterface;
use Mcp\Client\Handler\Request\ElicitationRequestHandler;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\ElicitResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End to end over a real transport: the official PHP MCP SDK client spawns
 * `bin/console mcp:server` (STDIO) and talks to it like Claude Desktop would.
 * Covers the human-only ruling (elicitation), resources, prompts and the MCP App.
 */
final class McpStdioEndToEndTest extends KernelTestCase
{
    /** @var list<ElicitRequest> */
    private array $forms = [];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->forms = [];
    }

    public function testTheHumanRulesThroughTheElicitationFormAndOnlyTheirAnswerIsRecorded(): void
    {
        [$case] = $this->arguedDemoCase();
        $client = $this->connect(new ElicitResult(ElicitAction::Accept, ['decision' => 'needs_review', 'reason' => 'Verify the menu evidence before outreach.']));

        $tools = [];
        foreach ($client->listTools()->tools as $tool) {
            $tools[$tool->name] = $tool;
        }
        self::assertArrayHasKey('request_ruling', $tools);
        self::assertSame(['caseId'], array_keys((array) $tools['request_ruling']->inputSchema['properties']), 'The model can only name the case, never the decision.');

        $result = $this->call($client, 'request_ruling', ['caseId' => $case->getId()]);
        $client->disconnect();

        self::assertSame('ruled', $result['outcome']);
        self::assertCount(1, $this->forms, 'Exactly one form was shown to the human.');
        self::assertStringContainsString('should your team pursue Court Cafe?', $this->forms[0]->message);
        self::assertStringContainsString('[struck] Guaranteed to sign this week.', $this->forms[0]->message);

        $this->entityManager->clear();
        $decision = $this->entityManager->getRepository(ProspectDecision::class)->findOneBy([]);
        self::assertInstanceOf(ProspectDecision::class, $decision);
        self::assertSame(ProspectDecision::DECISION_NEEDS_REVIEW, $decision->getDecision());
        self::assertSame('Evidence Court #'.$case->getId().' (ruled in an MCP client): Verify the menu evidence before outreach.', $decision->getReason());
        self::assertSame(CourtCase::STATUS_RULED, $this->entityManager->getRepository(CourtCase::class)->find($case->getId())->getStatus());
    }

    public function testDecliningTheFormRecordsNothing(): void
    {
        [$case] = $this->arguedDemoCase();
        $client = $this->connect(new ElicitResult(ElicitAction::Decline));

        $result = $this->call($client, 'request_ruling', ['caseId' => $case->getId()]);
        $client->disconnect();

        self::assertSame('declined', $result['outcome']);
        self::assertSame(0, $this->entityManager->getRepository(ProspectDecision::class)->count([]));
        $this->entityManager->clear();
        self::assertSame(CourtCase::STATUS_ARGUED, $this->entityManager->getRepository(CourtCase::class)->find($case->getId())->getStatus());
    }

    public function testAClientWithoutFormsIsSentToTheAppInsteadOfRuling(): void
    {
        [$case] = $this->arguedDemoCase();
        $client = $this->connect(null);

        $result = $this->call($client, 'request_ruling', ['caseId' => $case->getId()]);
        $client->disconnect();

        self::assertSame('unsupported', $result['outcome']);
        self::assertSame([], $this->forms);
        self::assertSame(0, $this->entityManager->getRepository(ProspectDecision::class)->count([]));
    }

    public function testResourcesPromptsAndTheHearingAppAreServed(): void
    {
        [$case, $prospect, $evidence] = $this->arguedDemoCase();
        $client = $this->connect(null);

        $resources = array_map(static fn ($r) => $r->uri, $client->listResources()->resources);
        sort($resources);
        self::assertSame(['prospectos://court/docket', 'ui://evidence-court/hearing'], $resources);
        self::assertSame(['prospectos://court/{caseId}', 'prospectos://lead/{leadId}/evidence'], array_map(static fn ($t) => $t->uriTemplate, $client->listResourceTemplates()->resourceTemplates));

        $hearing = json_decode($client->readResource('prospectos://court/'.$case->getId())->contents[0]->text, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Court Cafe', $hearing['lead']['name']);
        self::assertSame('struck', $hearing['advocate']['claims'][1]['ruling']);

        $file = json_decode($client->readResource('prospectos://lead/'.$prospect->getId().'/evidence')->contents[0]->text, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('E'.$evidence->getId(), $file['evidence'][0]['citation']);

        self::assertSame(['cross_examine', 'brief_me'], array_map(static fn ($p) => $p->name, $client->listPrompts()->prompts));
        $brief = $client->getPrompt('brief_me', ['caseId' => (string) $case->getId()]);
        self::assertSame('resource', $brief->messages[0]->content->type);
        self::assertSame('prospectos://court/'.$case->getId(), $brief->messages[0]->content->resource->uri);
        $cross = $client->getPrompt('cross_examine', ['leadId' => (string) $prospect->getId()]);
        self::assertStringContainsString('call request_ruling', $cross->messages[0]->content->text);

        $app = $client->readResource('ui://evidence-court/hearing')->contents[0];
        self::assertSame('text/html;profile=mcp-app', $app->mimeType);
        self::assertStringContainsString('ui/initialize', $app->text);

        $card = $this->call($client, 'show_court_case', ['caseId' => $case->getId()]);
        $client->disconnect();
        self::assertStringContainsString('Should we pursue Court Cafe?', $card['html']);
        self::assertStringContainsString('data-call="rule_from_card"', $card['html']);
        self::assertSame($case->getId(), $card['hearing']['case_id']);
    }

    private function connect(?ElicitResult $answer): Client
    {
        $builder = (new Builder())
            ->setClientInfo('prospectos-e2e', '1.0')
            ->setProtocolVersion(ProtocolVersion::V2025_11_25)
            ->setRequestTimeout(60);
        if (null !== $answer) {
            $forms = &$this->forms;
            $builder
                ->setCapabilities(new ClientCapabilities(elicitation: true))
                ->addRequestHandler(new ElicitationRequestHandler(new class($answer, $forms) implements ElicitationCallbackInterface {
                    /** @param list<ElicitRequest> $forms */
                    public function __construct(private readonly ElicitResult $answer, private array &$forms)
                    {
                    }

                    public function __invoke(ElicitRequest $request): ElicitResult
                    {
                        $this->forms[] = $request;

                        return $this->answer;
                    }
                }));
        }
        $client = $builder->build();
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $client->connect(new StdioTransport(\PHP_BINARY, [$projectDir.'/bin/console', 'mcp:server', '--env=test', '--no-debug'], $projectDir));

        return $client;
    }

    /** @return array<string, mixed> */
    private function call(Client $client, string $tool, array $arguments): array
    {
        $result = $client->callTool($tool, $arguments);
        self::assertFalse($result->isError, $result->content[0]->text ?? 'tool error');

        return json_decode($result->content[0]->text, true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return array{CourtCase, Prospect, EvidenceItem} */
    private function arguedDemoCase(): array
    {
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE court_case, prospect_decision, evidence_item, score_snapshot, prospect, research_run, workspace_membership, ai_provider_setting, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('14 Technologies Demo', 'demo');
        $owner = new User('demo-owner@example.test');
        $owner->setPassword('unused-e2e-password');
        $run = new ResearchRun($workspace, 'Find cafes without online ordering', null, ResearchRun::SOURCE_DEMO);
        $run->complete(1);
        $prospect = new Prospect($workspace, $run, 'court-cafe', 'Court Cafe', 'Cafe', 'Islamabad');
        $evidence = new EvidenceItem($workspace, $prospect, $run, 'menu', 'The menu is a downloadable PDF.', 'Court Cafe menu', 'Download our PDF menu here.');
        foreach ([$workspace, $owner, new WorkspaceMembership($workspace, $owner, WorkspaceMembership::ROLE_OWNER), $run, $prospect, $evidence] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $case = new CourtCase($workspace, $prospect, null, CourtCase::VIA_MCP);
        $claim = static fn (string $ruling, string $text, array $citations, string $note): array => ['ruling' => $ruling, 'text' => $text, 'citations' => $citations, 'note' => $note, 'read' => [] !== $citations];
        $case->recordHearing('Test', 'mock-model', [
            'brief' => [], 'score' => 64, 'coverage' => 75, 'precedents' => 0,
            'advocate' => ['raw' => '', 'tool_calls' => [['tool' => 'read_evidence', 'argument' => 'E'.$evidence->getId()]], 'admitted' => 1, 'struck' => 1, 'claims' => [
                $claim('admitted', 'The menu is only a downloadable PDF.', ['E'.$evidence->getId()], 'Admitted: backed by saved evidence.'),
                $claim('struck', 'Guaranteed to sign this week.', [], 'Struck: no evidence cited.'),
            ]],
            'skeptic' => ['raw' => '', 'tool_calls' => [], 'admitted' => 1, 'struck' => 0, 'claims' => [
                $claim('admitted', 'Nobody has confirmed who owns the menu.', ['E'.$evidence->getId()], 'Admitted: backed by saved evidence.'),
            ]],
        ], 1200);
        $this->entityManager->persist($case);
        $this->entityManager->flush();

        return [$case, $prospect, $evidence];
    }
}
