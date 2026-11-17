<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CourtCase;
use App\Entity\EvidenceItem;
use App\Entity\ProspectDecision;
use App\Entity\ResearchRun;
use App\Service\AiProviderConfiguration;
use App\Service\CurrentWorkspaceProvider;
use App\Service\DashboardData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(DashboardData $dashboard, CurrentWorkspaceProvider $workspaceProvider, AiProviderConfiguration $ai, EntityManagerInterface $entityManager): Response
    {
        $workspace = $workspaceProvider->get();
        $cases = $entityManager->getRepository(CourtCase::class);

        // The docket: argued hearings that only a person can close.
        $docket = array_map(static function (CourtCase $case): array {
            $t = $case->getTranscript();

            return [
                'id' => $case->getId(),
                'lead' => $case->getProspect()->getName(),
                'for' => (int) ($t['advocate']['admitted'] ?? 0),
                'against' => (int) ($t['skeptic']['admitted'] ?? 0),
                'via' => $case->getOpenedVia(),
                'opened' => $case->getCreatedAt(),
            ];
        }, $cases->findBy(['workspace' => $workspace, 'status' => CourtCase::STATUS_ARGUED], ['createdAt' => 'DESC'], 5));

        return $this->render('dashboard/index.html.twig', [
            ...$dashboard->dashboard($workspace),
            'aiReady' => $ai->isReady($workspace),
            'aiLabel' => $ai->isReady($workspace) ? $ai->providerName($workspace).' · '.$ai->modelName($workspace) : null,
            'hearings' => $cases->count(['workspace' => $workspace]),
            'awaitingRuling' => $cases->count(['workspace' => $workspace, 'status' => CourtCase::STATUS_ARGUED]),
            'docket' => $docket,
            'pipeline' => [
                'runs' => $entityManager->getRepository(ResearchRun::class)->count(['workspace' => $workspace]),
                'sources' => $entityManager->getRepository(EvidenceItem::class)->count(['workspace' => $workspace]),
                'rulings' => $entityManager->getRepository(ProspectDecision::class)->count(['workspace' => $workspace]),
            ],
        ]);
    }
}
