<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\CourtCase;
use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\ResearchRun;
use App\Entity\ScoreSnapshot;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Service\Court\CitationClerk;
use App\Service\Court\EvidenceCourt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EvidenceCourtTest extends WebTestCase
{
    public function testCounselUseEvidenceToolsAndTheClerkStrikesBadClaims(): void
    {
        static::createClient();
        [, , , $prospect, $evidence] = $this->makeCase();
        $evidenceId = (int) $evidence->getId();
        $requests = 0;
        $toolResultSeen = false;
        $platform = MockPlatformFactory::createPlatform(function (Model $model, $input) use (&$requests, &$toolResultSeen, $evidenceId) {
            ++$requests;
            if (1 === $requests) {
                return new ToolCallResult([new ToolCall('call_1', 'read_evidence', ['evidence_id' => 'E'.$evidenceId])]);
            }
            if (2 === $requests) {
                $toolResultSeen = str_contains(json_encode($input, JSON_THROW_ON_ERROR), 'downloadable PDF menu');

                return new TextResult("- The menu is only a downloadable PDF. [E{$evidenceId}]\n- They will surely sign this month.");
            }

            if (3 === $requests) {
                return new TextResult("- To 1: The menu is only a downloadable PDF. [E{$evidenceId}]\n- To 1: The source is synthetic and unverified. [E{$evidenceId}]\n- Also: Their budget is tiny. [E424242]");
            }

            return new TextResult("- To S2: Fair point, it is sample data. A quick call would confirm the menu. [E{$evidenceId}]");
        });

        $court = static::getContainer()->get(EvidenceCourt::class);
        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model');

        self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), (string) $case->getFailure());
        self::assertSame(4, $requests, 'Advocate: tool call + opening; Skeptic: reply; Advocate: rebuttal.');
        self::assertTrue($toolResultSeen, 'The read_evidence result is passed back to the model.');

        $advocate = $case->getTranscript()['advocate'];
        self::assertSame([['tool' => 'read_evidence', 'argument' => 'E'.$evidenceId]], $advocate['tool_calls']);
        self::assertSame([CitationClerk::ADMITTED, CitationClerk::STRUCK], array_column($advocate['claims'], 'ruling'));
        self::assertTrue($advocate['claims'][0]['read']);

        $skeptic = $case->getTranscript()['skeptic'];
        self::assertSame([CitationClerk::STRUCK, CitationClerk::ADMITTED, CitationClerk::STRUCK], array_column($skeptic['claims'], 'ruling'));
        self::assertStringContainsString('repeats the Advocate', $skeptic['claims'][0]['note']);
        self::assertStringContainsString('E424242', $skeptic['claims'][2]['note']);
        self::assertSame(1, $skeptic['admitted']);
        // The conversation is threaded: "To 1:" answers Advocate point 1, "Also:" is a new concern.
        self::assertSame([0, 0, null], array_column($skeptic['claims'], 'reply_to'));
        self::assertSame('The source is synthetic and unverified.', $skeptic['claims'][1]['text'], 'Reply prefixes are removed from the message.');

        $rebuttal = $case->getTranscript()['rebuttal'];
        self::assertSame(1, $rebuttal['claims'][0]['reply_to'], '"To S2:" answers the second Skeptic reply.');
        self::assertSame(CitationClerk::ADMITTED, $rebuttal['claims'][0]['ruling']);
        self::assertStringStartsWith('Fair point', $rebuttal['claims'][0]['text']);
        self::assertSame('Skeptic reply 2', EvidenceCourt::describe($case)['rebuttal']['claims'][0]['replies_to']);
    }

    public function testAFailedRebuttalStillLeavesTheOpeningAndReply(): void
    {
        static::createClient();
        [, , , $prospect, $evidence] = $this->makeCase();
        $requests = 0;
        $platform = MockPlatformFactory::createPlatform(function () use (&$requests, $evidence) {
            return match (++$requests) {
                1 => new TextResult('- The menu is only a downloadable PDF. [E'.$evidence->getId().']'),
                2 => new TextResult('- To 1: Nobody has confirmed who runs the cafe. [E'.$evidence->getId().']'),
                default => throw new \RuntimeException('provider hiccup'),
            };
        });
        $container = static::getContainer();
        $court = new EvidenceCourt($container->get(EntityManagerInterface::class), $container->get(\App\Service\AiProviderConfiguration::class),
            $container->get(\App\Service\ProspectMemory::class), new CitationClerk(), new \Psr\Log\NullLogger(), static function (): void {});

        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model');

        self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), (string) $case->getFailure());
        self::assertSame(3, $requests, 'The rebuttal was attempted.');
        self::assertSame(1, $case->getTranscript()['skeptic']['admitted']);
        self::assertNull($case->getTranscript()['rebuttal']);
        self::assertNull(EvidenceCourt::describe($case)['rebuttal']);
    }

    public function testHearingReportsStrictlyIncreasingProgressIncludingEachToolCall(): void
    {
        static::createClient();
        [, , , $prospect, $evidence] = $this->makeCase();
        $requests = 0;
        $platform = MockPlatformFactory::createPlatform(function () use (&$requests, $evidence) {
            if (1 === ++$requests) {
                return new ToolCallResult([new ToolCall('call_1', 'read_evidence', ['evidence_id' => 'E'.$evidence->getId()])]);
            }

            return new TextResult('- The menu is a downloadable PDF. [E'.$evidence->getId().']');
        });
        $updates = [];

        $court = static::getContainer()->get(EvidenceCourt::class);
        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model', static function (float $step, string $message) use (&$updates): void {
            $updates[] = [$step, $message];
        });

        self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), (string) $case->getFailure());
        $steps = array_column($updates, 0);
        $sorted = $steps;
        sort($sorted);
        self::assertSame($sorted, $steps, 'MCP progress must never go backwards.');
        self::assertCount(\count($steps), array_unique(array_map('strval', $steps)), 'MCP progress must strictly increase.');
        self::assertSame((float) EvidenceCourt::PROGRESS_STEPS, end($steps));
        self::assertContains('Advocate called read_evidence(E'.$evidence->getId().')', array_column($updates, 1));
        self::assertStringContainsString('Awaiting a human ruling', end($updates)[1]);
    }

    public function testProviderRateLimitIsWaitedOutAndTheHearingCompletes(): void
    {
        static::createClient();
        [, , , $prospect, $evidence] = $this->makeCase();
        $requests = 0;
        $platform = MockPlatformFactory::createPlatform(function () use (&$requests, $evidence) {
            ++$requests;
            if (1 === $requests) {
                throw new \Symfony\AI\Platform\Exception\RateLimitExceededException(3, 'tokens per minute');
            }

            return new TextResult('- The menu is a downloadable PDF. [E'.$evidence->getId().']');
        });
        $waits = [];
        $container = static::getContainer();
        $court = new EvidenceCourt(
            $container->get(EntityManagerInterface::class),
            $container->get(\App\Service\AiProviderConfiguration::class),
            $container->get(\App\Service\ProspectMemory::class),
            new CitationClerk(),
            new \Psr\Log\NullLogger(),
            static function (int $seconds) use (&$waits): void { $waits[] = $seconds; },
        );

        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model');

        self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), (string) $case->getFailure());
        self::assertSame([4], $waits, 'Waits the provider-requested 3 s plus a 1 s margin, once.');
        self::assertSame(3, $requests);
    }

    public function testMalformedToolCallFromTheProviderIsRetried(): void
    {
        static::createClient();
        [, , , $prospect, $evidence] = $this->makeCase();
        $requests = 0;
        $platform = MockPlatformFactory::createPlatform(function () use (&$requests, $evidence) {
            if (1 === ++$requests) {
                throw new \Symfony\AI\Platform\Exception\BadRequestException("Parsing failed. The model generated output that could not be parsed. See 'failed_generation' for more details.");
            }

            return new TextResult('- The menu is a downloadable PDF. [E'.$evidence->getId().']');
        });
        $container = static::getContainer();
        $court = new EvidenceCourt($container->get(EntityManagerInterface::class), $container->get(\App\Service\AiProviderConfiguration::class),
            $container->get(\App\Service\ProspectMemory::class), new CitationClerk(), new \Psr\Log\NullLogger(), static function (): void {});

        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model');

        self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), (string) $case->getFailure());
        self::assertSame(3, $requests);
    }

    public function testProviderFailureIsRecordedInsteadOfThrown(): void
    {
        static::createClient();
        [, , , $prospect] = $this->makeCase();
        $platform = MockPlatformFactory::createPlatform(static fn () => throw new \RuntimeException('provider down'));

        $court = static::getContainer()->get(EvidenceCourt::class);
        $case = $court->hear($court->open($prospect, null), $platform, 'mock-model');

        self::assertSame(CourtCase::STATUS_FAILED, $case->getStatus());
        self::assertStringContainsString('could not complete the hearing', (string) $case->getFailure());
    }

    public function testOnlyAHumanRulingInTheAppRecordsTeamMemory(): void
    {
        $client = static::createClient();
        [$entityManager, $user, $workspace, $prospect, $evidence] = $this->makeCase();
        $court = static::getContainer()->get(EvidenceCourt::class);
        $case = $court->hear($court->open($prospect, $user), MockPlatformFactory::createPlatform('- PDF menu only. [E'.$evidence->getId().']'), 'mock-model');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/court/'.$case->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Should we pursue Court Cafe?');
        self::assertSelectorTextContains('.chat-msg.from-advocate.is-admitted', 'PDF menu only.');
        self::assertSelectorTextContains('.chat-msg.from-advocate .chat-clerk', 'Checked: backed by the proof shown');
        self::assertSelectorTextContains('.chat-bottom', 'Where it landed');

        $client->submit($crawler->filter('form.court-ruling-form')->form([
            'decision' => ProspectDecision::DECISION_FINALIZED,
            'reason' => 'Admitted PDF-menu finding fits our offer.',
        ]));
        self::assertResponseRedirects('/court/'.$case->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.court-verdict', 'Ruling: Pursue');

        $entityManager->clear();
        $decision = $entityManager->getRepository(ProspectDecision::class)->findOneBy(['workspace' => $workspace->getId()]);
        self::assertInstanceOf(ProspectDecision::class, $decision);
        self::assertStringStartsWith('Evidence Court #'.$case->getId().':', $decision->getReason());
        self::assertSame(Prospect::STATUS_FINALIZED, $entityManager->getRepository(Prospect::class)->find($prospect->getId())->getStatus());
        self::assertSame(CourtCase::STATUS_RULED, $entityManager->getRepository(CourtCase::class)->find($case->getId())->getStatus());
        self::assertNull(EvidenceCourt::describe($entityManager->getRepository(CourtCase::class)->find($case->getId()))['how_to_rule']);
    }

    public function testCourtListShowsAHearingThatFailedPartway(): void
    {
        $client = static::createClient();
        [$entityManager, $user, , $prospect] = $this->makeCase();
        $case = static::getContainer()->get(EvidenceCourt::class)->open($prospect, $user);
        // The Advocate spoke, then the provider failed: the partial transcript has no Skeptic turn.
        $case->recordProgress(['brief' => [], 'score' => null, 'coverage' => null, 'precedents' => 0, 'advocate' => ['admitted' => 1, 'struck' => 0, 'claims' => [], 'tool_calls' => []]], 'skeptic', 'is reading the file');
        $case->fail('The AI provider could not complete the hearing (RateLimitExceededException).');
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/court');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/court/'.$case->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.court-failed', 'could not finish');
    }

    public function testCasesAreInvisibleToOtherWorkspaces(): void
    {
        $client = static::createClient();
        [$entityManager, , , $prospect] = $this->makeCase();
        $court = static::getContainer()->get(EvidenceCourt::class);
        $case = $court->open($prospect, null);

        $outsiderWorkspace = new Workspace('Outsider', 'outsider-court');
        $outsider = new User('outsider@example.test');
        $outsider->setPassword('unused');
        $entityManager->persist($outsiderWorkspace);
        $entityManager->persist($outsider);
        $entityManager->persist(new WorkspaceMembership($outsiderWorkspace, $outsider, WorkspaceMembership::ROLE_OWNER));
        $entityManager->flush();

        $client->loginUser($outsider);
        $client->request('GET', '/court/'.$case->getId());
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/court/'.$case->getId().'/ruling', ['_token' => 'x', 'decision' => 'finalized', 'reason' => 'Should not work']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOpeningCourtWithoutAiSendsUserToSetup(): void
    {
        $client = static::createClient();
        [, $user, , $prospect] = $this->makeCase();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/court');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Connect an AI model first');

        $client->request('POST', '/prospects/'.$prospect->getId().'/court', ['_token' => $crawler->filter('input[name=_token]')->attr('value')]);
        self::assertResponseRedirects('/integrations/ai');
    }

    /** @return array{EntityManagerInterface, User, Workspace, Prospect, EvidenceItem} */
    private function makeCase(): array
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE court_case, prospect_decision, evidence_item, score_snapshot, prospect, research_run, workspace_membership, ai_provider_setting, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new Workspace('Court workspace', 'court-workspace');
        $user = new User('court-owner@example.test');
        $user->setPassword('unused-court-password');
        $run = new ResearchRun($workspace, 'Find cafes without online ordering', null, ResearchRun::SOURCE_DEMO);
        $run->complete(1);
        $prospect = new Prospect($workspace, $run, 'court-cafe', 'Court Cafe', 'Cafe', 'Islamabad');
        $evidence = new EvidenceItem($workspace, $prospect, $run, 'menu', 'The menu is a downloadable PDF menu.', 'Court Cafe menu', 'Download our downloadable PDF menu here.');
        $entityManager->persist($workspace);
        $entityManager->persist($user);
        $entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $entityManager->persist($run);
        $entityManager->persist($prospect);
        $entityManager->persist($evidence);
        $entityManager->persist(new ScoreSnapshot($workspace, $prospect, $run, 64, 75, ['icpFit' => ['value' => 80, 'weight' => 25], 'contactability' => ['value' => null, 'weight' => 15]]));
        $entityManager->flush();

        return [$entityManager, $user, $workspace, $prospect, $evidence];
    }
}
