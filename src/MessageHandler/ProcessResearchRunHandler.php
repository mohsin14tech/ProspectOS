<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\ScoreSnapshot;
use App\Message\ProcessResearchRun;
use App\Service\DemoProspectDiscovery;
use App\Service\OpportunityScorer;
use App\Service\SearXNGDiscoveryProvider;
use App\Service\WebsiteInspector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessResearchRunHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoProspectDiscovery $demoDiscovery,
        private readonly SearXNGDiscoveryProvider $webDiscovery,
        private readonly OpportunityScorer $scorer,
        private readonly WebsiteInspector $websiteInspector,
    ) {
    }

    public function __invoke(ProcessResearchRun $message): void
    {
        $run = $this->entityManager->getRepository(ResearchRun::class)->find($message->researchRunId);
        if (!$run instanceof ResearchRun || $run->getStatus() === ResearchRun::STATUS_COMPLETED) {
            return;
        }

        try {
            $run->start();
            $this->entityManager->flush();
            $count = 0;

            if ($run->getWebsiteUrl() !== null) {
                $inspection = $this->websiteInspector->inspect($run->getWebsiteUrl());
                $name = mb_substr($inspection['title'] !== '' ? $inspection['title'] : $inspection['host'], 0, 180);
                $slug = 'website-'.substr(hash('sha256', $inspection['host']), 0, 32);
                $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy([
                    'workspace' => $run->getWorkspace(),
                    'slug' => $slug,
                ]);
                if (!$prospect instanceof Prospect) {
                    $prospect = new Prospect($run->getWorkspace(), $run, $slug, $name, 'Website submission', 'Unspecified');
                    $this->entityManager->persist($prospect);
                } else {
                    $prospect->updateFromRun($run);
                }

                $claim = $inspection['title'] === '' ? 'The public page returned no title element.' : 'Page title observed: '.$inspection['title'];
                if ($inspection['description'] !== '') {
                    $claim .= ' Page description observed: '.$inspection['description'];
                }
                $excerpt = $inspection['excerpt'] !== '' ? $inspection['excerpt'] : 'Page loaded successfully; no text excerpt was available.';
                $evidence = $this->entityManager->getRepository(EvidenceItem::class)->findOneBy([
                    'researchRun' => $run,
                    'prospect' => $prospect,
                    'findingType' => 'website_metadata',
                ]);
                if (!$evidence instanceof EvidenceItem) {
                    $evidence = new EvidenceItem($run->getWorkspace(), $prospect, $run, 'website_metadata', $claim, 'Public page metadata', $excerpt);
                    $evidence->markObservedWebsite($inspection['url'], $excerpt);
                    $this->entityManager->persist($evidence);
                }

                $score = $this->scorer->calculate(['evidenceQuality' => 60]);
                $snapshot = $this->entityManager->getRepository(ScoreSnapshot::class)->findOneBy(['researchRun' => $run, 'prospect' => $prospect]);
                if (!$snapshot instanceof ScoreSnapshot) {
                    $this->entityManager->persist(new ScoreSnapshot($run->getWorkspace(), $prospect, $run, $score['score'], $score['coverage'], $score['breakdown']));
                }
                $this->entityManager->flush();
                $run->complete(1);
                $this->entityManager->flush();

                return;
            }

            $discovery = $run->getSourceMode() === ResearchRun::SOURCE_WEB_SEARCH
                ? $this->webDiscovery
                : $this->demoDiscovery;

            foreach ($discovery->discover($run->getObjective()) as $fixture) {
                $run->setStage($run->getSourceMode() === ResearchRun::SOURCE_WEB_SEARCH ? 'saving_search_candidates' : 'saving_demo_evidence');
                $prospect = $this->entityManager->getRepository(Prospect::class)->findOneBy([
                    'workspace' => $run->getWorkspace(),
                    'slug' => $fixture['slug'],
                ]);

                if (!$prospect instanceof Prospect) {
                    $prospect = new Prospect(
                        $run->getWorkspace(),
                        $run,
                        $fixture['slug'],
                        $fixture['name'],
                        $fixture['category'],
                        $fixture['location'],
                    );
                    $this->entityManager->persist($prospect);
                } else {
                    $prospect->updateFromRun($run);
                }

                $this->entityManager->flush();

                $evidence = $this->entityManager->getRepository(EvidenceItem::class)->findOneBy([
                    'researchRun' => $run,
                    'prospect' => $prospect,
                    'findingType' => $fixture['findingType'],
                ]);
                if (!$evidence instanceof EvidenceItem) {
                    $evidence = new EvidenceItem(
                        $run->getWorkspace(),
                        $prospect,
                        $run,
                        $fixture['findingType'],
                        $fixture['claim'],
                        $fixture['sourceTitle'] ?? 'Synthetic demo fixture',
                        $fixture['excerpt'],
                    );
                    if (isset($fixture['sourceUrl'])) {
                        $evidence->markSearchResult($fixture['sourceUrl'], $fixture['excerpt'], $fixture['confidence'] ?? 0.35);
                    }
                    $this->entityManager->persist($evidence);
                }

                $score = $this->scorer->calculate($fixture['dimensions']);
                $snapshot = $this->entityManager->getRepository(ScoreSnapshot::class)->findOneBy([
                    'researchRun' => $run,
                    'prospect' => $prospect,
                ]);
                if (!$snapshot instanceof ScoreSnapshot) {
                    $this->entityManager->persist(new ScoreSnapshot(
                        $run->getWorkspace(),
                        $prospect,
                        $run,
                        $score['score'],
                        $score['coverage'],
                        $score['breakdown'],
                    ));
                }

                $this->entityManager->flush();
                ++$count;
            }

            $run->complete($count);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $run->fail();
            $this->entityManager->flush();
            throw $exception;
        }
    }
}
