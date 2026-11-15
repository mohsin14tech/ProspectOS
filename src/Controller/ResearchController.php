<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EvidenceItem;
use App\Entity\Prospect;
use App\Entity\ResearchRun;
use App\Entity\ScoreSnapshot;
use App\Message\ProcessResearchRun;
use App\Service\CurrentWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ResearchController extends AbstractController
{
    #[Route('/research', name: 'app_research', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        CurrentWorkspaceProvider $workspaceProvider,
        #[Autowire(env: 'SEARCH_PROVIDER')] string $defaultSearchProvider,
    ): Response {
        if ($request->isMethod('POST')) {
            $objective = trim((string) $request->request->get('objective', ''));
            $websiteUrl = trim((string) $request->request->get('website_url', ''));
            $websiteUrl = $websiteUrl === '' ? null : $websiteUrl;
            $sourceMode = (string) $request->request->get('source', $defaultSearchProvider);

            if (!$this->isCsrfTokenValid('research_objective', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Your session expired. Refresh the page and try again.');

                return $this->redirectToRoute('app_research');
            }

            if (mb_strlen($objective) < 12 || mb_strlen($objective) > 500) {
                $this->addFlash('error', 'Add an objective between 12 and 500 characters.');

                return $this->redirectToRoute('app_research');
            }

            if (!in_array($sourceMode, [ResearchRun::SOURCE_DEMO, ResearchRun::SOURCE_WEB_SEARCH], true)) {
                $this->addFlash('error', 'Choose web search or clearly labelled demo data.');

                return $this->redirectToRoute('app_research');
            }

            $websiteScheme = $websiteUrl === null ? null : strtolower((string) parse_url($websiteUrl, PHP_URL_SCHEME));
            if ($websiteUrl !== null && (mb_strlen($websiteUrl) > 2048 || filter_var($websiteUrl, FILTER_VALIDATE_URL) === false || !in_array($websiteScheme, ['http', 'https'], true))) {
                $this->addFlash('error', 'Enter a valid HTTP or HTTPS website URL, or leave the field blank.');

                return $this->redirectToRoute('app_research');
            }

            $run = new ResearchRun($workspaceProvider->get(), $objective, $websiteUrl, $sourceMode);
            $entityManager->persist($run);
            $entityManager->flush();

            try {
                $messageBus->dispatch(new ProcessResearchRun($run->getId()));
                $request->getSession()->set('last_research_objective', $objective);
                if ($websiteUrl !== null) {
                    $request->getSession()->set('last_research_website_url', $websiteUrl);
                }
                $this->addFlash('success', $websiteUrl !== null
                    ? 'Website inspection queued. Only a public HTML page will be fetched; this does not search or verify a business.'
                    : ($sourceMode === ResearchRun::SOURCE_WEB_SEARCH
                        ? 'Web search queued. Results are candidates from upstream search engines; business fit is not verified.'
                        : 'Demo research queued. It uses fictional fixtures and does not inspect live websites.'));
            } catch (\Throwable) {
                $run->fail();
                $entityManager->flush();
                $this->addFlash('error', 'The research run could not be queued. Check the local worker and try again.');
            }

            return $this->redirectToRoute('app_research');
        }

        $workspace = $workspaceProvider->get();
        $runs = $entityManager->getRepository(ResearchRun::class)->findBy(
            ['workspace' => $workspace],
            ['createdAt' => 'DESC'],
            5,
        );

        $latestResults = [];
        if (isset($runs[0]) && $runs[0]->getStatus() === ResearchRun::STATUS_COMPLETED) {
            $prospects = $entityManager->getRepository(Prospect::class)->findBy(
                ['researchRun' => $runs[0]],
                ['name' => 'ASC'],
            );
            foreach ($prospects as $prospect) {
                $latestResults[] = [
                    'prospect' => $prospect,
                    'evidence' => $entityManager->getRepository(EvidenceItem::class)->findOneBy(
                        ['researchRun' => $runs[0], 'prospect' => $prospect],
                        ['retrievedAt' => 'DESC'],
                    ),
                    'score' => $entityManager->getRepository(ScoreSnapshot::class)->findOneBy(
                        ['researchRun' => $runs[0], 'prospect' => $prospect],
                    ),
                ];
            }
        }

        return $this->render('research/index.html.twig', [
            'workspace' => $workspace,
            'lastObjective' => $request->getSession()->get('last_research_objective'),
            'lastWebsiteUrl' => $request->getSession()->get('last_research_website_url'),
            'defaultSearchProvider' => $defaultSearchProvider,
            'runs' => $runs,
            'latestResults' => $latestResults,
        ]);
    }
}
