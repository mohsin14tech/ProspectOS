<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Service\Court\CitationClerk;
use App\Service\Court\EvidenceCourt;
use App\Service\Court\RecordedHearings;
use App\Service\DemoWorkspaceProvider;
use App\Service\DemoWorkspaceSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The zero-key demo path a judge hits on a fresh clone: no AI provider, freshly seeded
 * demo workspace, committed recordings in config/demo/recorded_hearings.json.
 */
final class RecordedHearingReplayTest extends KernelTestCase
{
    public function testEveryDemoLeadReplaysAFullConversationOnAFreshInstallWithoutAnyKey(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE TABLE court_case, prospect_decision, evidence_item, score_snapshot, prospect, research_run, workspace_membership, ai_provider_setting, app_user, workspace RESTART IDENTITY CASCADE');
        $container->get(DemoWorkspaceSeeder::class)->seed();
        $workspace = $container->get(DemoWorkspaceProvider::class)->get();
        $court = $container->get(EvidenceCourt::class);

        foreach (['juniper-table', 'northstar-dental', 'olive-and-grain'] as $slug) {
            $prospect = $em->getRepository(Prospect::class)->findOneBy(['workspace' => $workspace, 'slug' => $slug]);
            self::assertInstanceOf(Prospect::class, $prospect);
            self::assertTrue($court->isReplay($prospect), $slug.' has a recording and no AI is connected.');
            self::assertTrue($court->isReady($prospect));

            $case = $court->hear($court->open($prospect, null, CourtCase::VIA_MCP));

            self::assertSame(CourtCase::STATUS_ARGUED, $case->getStatus(), $slug.': '.$case->getFailure());
            self::assertSame(RecordedHearings::PROVIDER, $case->getProvider());
            $transcript = $case->getTranscript();
            self::assertNotNull($transcript['rebuttal'], $slug.' replays all three turns.');
            self::assertNotEmpty($transcript['advocate']['tool_calls'], 'Recorded tool calls really run against this database.');
            foreach (['advocate', 'skeptic', 'rebuttal'] as $turn) {
                foreach ($transcript[$turn]['claims'] as $claim) {
                    self::assertStringNotContainsString('not in this case file', $claim['note'], $slug.': every recorded citation resolves to a record on this install.');
                }
                self::assertGreaterThan(0, CitationClerk::admittedCount($transcript[$turn]['claims']), $slug.' '.$turn.' has admitted claims.');
            }
        }
    }

    public function testOtherWorkspacesNeverGetReplays(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE TABLE court_case, prospect_decision, evidence_item, score_snapshot, prospect, research_run, workspace_membership, ai_provider_setting, app_user, workspace RESTART IDENTITY CASCADE');
        $workspace = new \App\Entity\Workspace('Real team', 'real-team');
        $run = new \App\Entity\ResearchRun($workspace, 'x', null, \App\Entity\ResearchRun::SOURCE_DEMO);
        $prospect = new Prospect($workspace, $run, 'juniper-table', 'Juniper Table', 'Restaurant', 'Islamabad');
        foreach ([$workspace, $run, $prospect] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        self::assertFalse(static::getContainer()->get(EvidenceCourt::class)->isReady($prospect), 'A same-named lead outside the demo workspace needs a real AI provider.');
    }
}
