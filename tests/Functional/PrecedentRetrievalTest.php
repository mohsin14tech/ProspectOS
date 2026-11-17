<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\ResearchRun;
use App\Entity\User;
use App\Entity\Workspace;
use App\Service\Memory\LexicalVectorizer;
use App\Service\ProspectMemory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PrecedentRetrievalTest extends KernelTestCase
{
    public function testSimilarLeadsAreFoundThroughTheStoreWhenNothingMatchesExactly(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('TRUNCATE TABLE court_case, prospect_decision, evidence_item, score_snapshot, prospect, research_run, workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');

        $workspace = new Workspace('Memory workspace', 'memory-workspace');
        $other = new Workspace('Other workspace', 'other-memory-workspace');
        $user = new User('memory@example.test');
        $user->setPassword('unused');
        $run = new ResearchRun($workspace, 'Find cafes without online ordering', null, ResearchRun::SOURCE_DEMO);
        $otherRun = new ResearchRun($other, 'Find cafes without online ordering', null, ResearchRun::SOURCE_DEMO);
        $target = new Prospect($workspace, $run, 'cedar', 'Cedar Cafe', 'Independent cafe', 'Islamabad F-7');
        $cafe = new Prospect($workspace, $run, 'olive', 'Olive Cafe', 'Cafe and bakery', 'Islamabad F-6');
        $dentist = new Prospect($workspace, $run, 'north', 'Northstar Dental', 'Dental clinic', 'Rawalpindi');
        $foreign = new Prospect($other, $otherRun, 'foreign', 'Foreign Cafe', 'Independent cafe', 'Islamabad F-7');
        foreach ([$workspace, $other, $user, $run, $otherRun, $target, $cafe, $dentist, $foreign] as $entity) {
            $em->persist($entity);
        }
        $em->persist(new ProspectDecision($workspace, $cafe, $user, ProspectDecision::DECISION_FINALIZED, 'Cafe menu is a PDF only; good fit for online ordering.'));
        $em->persist(new ProspectDecision($workspace, $dentist, $user, ProspectDecision::DECISION_REJECTED, 'Dental clinics book by phone; not our market.'));
        $em->persist(new ProspectDecision($other, $foreign, $user, ProspectDecision::DECISION_FINALIZED, 'Private reason from another workspace.'));
        $em->flush();

        $found = static::getContainer()->get(ProspectMemory::class)->relevantDecisions($workspace, $target);

        self::assertNotEmpty($found);
        self::assertSame(['Olive Cafe'], array_map(static fn (ProspectDecision $d): string => $d->getProspect()->getName(), $found), 'The similar cafe is found; the unrelated dentist is not.');
        foreach ($found as $decision) {
            self::assertSame($workspace->getId(), $decision->getWorkspace()->getId(), 'Never another workspace\'s decisions.');
        }
    }

    public function testTheVectorizerIsDeterministicAndRanksRelatedTextCloser(): void
    {
        $v = new LexicalVectorizer();
        $cos = static fn (array $a, array $b): float => array_sum(array_map(static fn ($x, $y) => $x * $y, $a, $b));
        $query = $v->embed('Independent cafe in Islamabad')->getData();

        self::assertSame($query, $v->embed('Independent cafe in Islamabad')->getData());
        self::assertGreaterThan(
            $cos($query, $v->embed('Dental clinic in Rawalpindi')->getData()),
            $cos($query, $v->embed('Islamabad cafes and bakery')->getData()),
        );
        self::assertCount(LexicalVectorizer::DIMENSIONS, $query);
    }
}
