<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EvidenceItem;
use App\Entity\EvidenceSummary;
use App\Entity\Prospect;
use App\Entity\ScoreSnapshot;
use App\Service\CurrentWorkspaceProvider;
use App\Service\EvidenceSummaryGenerator;
use App\Service\AiProviderConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ProspectController extends AbstractController
{
    #[Route('/prospects/{id}', name: 'app_prospect_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager, CurrentWorkspaceProvider $workspaceProvider, AiProviderConfiguration $ai): Response
    {
        $workspace = $workspaceProvider->get();
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy([
            'id' => $id,
            'workspace' => $workspace,
        ]);

        if (!$prospect instanceof Prospect) {
            throw $this->createNotFoundException('Prospect not found.');
        }

        $evidence = $entityManager->getRepository(EvidenceItem::class)->findBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['retrievedAt' => 'DESC'],
            50,
        );
        $score = $entityManager->getRepository(ScoreSnapshot::class)->findOneBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['createdAt' => 'DESC'],
        );
        $summary = $entityManager->getRepository(EvidenceSummary::class)->findOneBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['createdAt' => 'DESC'],
        );

        return $this->render('prospect/show.html.twig', [
            'workspace' => $workspace,
            'prospect' => $prospect,
            'evidence' => $evidence,
            'score' => $score,
            'summary' => $summary,
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->providerName(),
            'aiModel' => $ai->modelName(),
            'aiTier' => $ai->tierLabel(),
            'aiDisclosure' => $ai->dataDisclosure(),
        ]);
    }

    #[Route('/prospects/{id}/analyze', name: 'app_prospect_analyze', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function analyze(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CurrentWorkspaceProvider $workspaceProvider,
        EvidenceSummaryGenerator $generator,
        RateLimiterFactory $aiSummaryLimiter,
        AiProviderConfiguration $ai,
    ): Response {
        $workspace = $workspaceProvider->get();
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$prospect instanceof Prospect) {
            throw $this->createNotFoundException('Prospect not found.');
        }
        if (!$this->isCsrfTokenValid('analyze_prospect_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the report and try again.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }
        if (!$ai->isConfigured()) {
            $this->addFlash('error', 'AI analysis is unavailable while AI is disabled or the selected provider is not configured.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        $evidence = $entityManager->getRepository(EvidenceItem::class)->findBy(
            ['workspace' => $workspace, 'prospect' => $prospect],
            ['retrievedAt' => 'DESC'],
            8,
        );
        if ([] === $evidence) {
            $this->addFlash('error', 'Save source evidence for this prospect before generating an AI summary.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }
        $limiter = $aiSummaryLimiter->create($this->getUser()->getUserIdentifier());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'This workspace user reached the limit of 10 AI summaries per hour. Try again later.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        try {
            $text = $generator->generate($prospect->getName(), $prospect->getResearchRun()->getObjective(), $evidence);
            $entityManager->persist(new EvidenceSummary(
                $workspace,
                $prospect,
                array_values(array_filter(array_map(static fn (EvidenceItem $item): ?int => $item->getId(), $evidence))),
                $generator->providerName(),
                $generator->modelName(),
                $text,
            ));
            $entityManager->flush();
            $this->addFlash('success', 'Evidence summary saved with citations. Review the linked source records before using it.');
        } catch (\Throwable) {
            $this->addFlash('error', 'The AI provider did not return a usable, correctly cited summary. Check the selected provider connection and try again.');
        }

        return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
    }
}
