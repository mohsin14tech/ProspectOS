<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardData;
use App\Service\CurrentWorkspaceProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(DashboardData $dashboard, CurrentWorkspaceProvider $workspaceProvider): Response
    {
        return $this->render('dashboard/index.html.twig', $dashboard->dashboard($workspaceProvider->get()));
    }
}
