<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SearXNGDiscoveryProvider;
use App\Service\CurrentWorkspaceProvider;
use App\Service\AiProviderConfiguration;
use App\Entity\GmailConnection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class IntegrationsController extends AbstractController
{
    #[Route('/integrations', name: 'app_integrations', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        SearXNGDiscoveryProvider $webSearch,
        CurrentWorkspaceProvider $workspaceProvider,
        AiProviderConfiguration $ai,
        EntityManagerInterface $entityManager,
        RateLimiterFactory $aiSummaryLimiter,
    ): Response {
        $workspace = $workspaceProvider->get();

        if ($request->isMethod('POST')) {
            $workspaceProvider->requireAdmin();
            if (!$this->isCsrfTokenValid('test_search', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Your session expired. Refresh the page and try again.');

                return $this->redirectToRoute('app_integrations');
            }

            $test = (string) $request->request->get('test', 'search');
            if ('ai' === $test) {
                if (!$ai->isConfigured($workspace)) {
                    $this->addFlash('error', 'AI is disabled or the selected provider is not configured. Open provider setup to review its settings.');
                } elseif (!$aiSummaryLimiter->create($this->getUser()->getUserIdentifier())->consume()->isAccepted()) {
                    $this->addFlash('error', 'This workspace user reached the limit of 10 AI actions per hour. Try again later.');
                } else {
                    try {
                        $actor = $this->getUser();
                        $latency = $ai->test($workspace, $actor instanceof User ? $actor : null);
                        $this->addFlash('success', sprintf('AI provider responded successfully: %s · %s · %d ms.', $ai->providerName($workspace), $ai->modelName($workspace), $latency));
                    } catch (\Throwable) {
                        $this->addFlash('error', 'AI provider test failed. Check the selected model, endpoint, key, and provider account limits.');
                    }
                }
            } else {
                try {
                    $results = $webSearch->discover('site:example.com Example Domain');
                    $this->addFlash('success', sprintf(
                        'The search API responded successfully and returned %d candidate result%s.',
                        count($results),
                        count($results) === 1 ? '' : 's',
                    ));
                } catch (\Throwable) {
                    $this->addFlash('error', 'Search test failed. Check the local search service and try again.');
                }
            }

            return $this->redirectToRoute('app_integrations');
        }

        return $this->render('integrations/index.html.twig', [
            'workspace' => $workspace,
            'searchEndpoint' => $webSearch->baseUrl(),
            'aiConfigured' => $ai->isConfigured($workspace),
            'aiReady' => $ai->isReady($workspace),
            'aiEnabled' => $ai->isEnabled($workspace),
            'aiProvider' => $ai->providerName($workspace),
            'aiModel' => $ai->modelName($workspace),
            'aiTier' => $ai->tierLabel($workspace),
            'aiDisclosure' => $ai->dataDisclosure($workspace),
            'gmailConnection' => $entityManager->getRepository(GmailConnection::class)->findOneBy(['workspace' => $workspace]),
        ]);
    }
}
