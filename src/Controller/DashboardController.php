<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CourtCase;
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

        return $this->render('dashboard/index.html.twig', [
            ...$dashboard->dashboard($workspace),
            'aiReady' => $ai->isReady($workspace),
            'aiLabel' => $ai->isReady($workspace) ? $ai->providerName($workspace).' · '.$ai->modelName($workspace) : null,
            'hearings' => $entityManager->getRepository(CourtCase::class)->count(['workspace' => $workspace]),
            'awaitingRuling' => $entityManager->getRepository(CourtCase::class)->count(['workspace' => $workspace, 'status' => CourtCase::STATUS_ARGUED]),
        ]);
    }
}
