<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CourtCase;
use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\User;
use App\Message\HearCourtCase;
use App\Service\AiProviderConfiguration;
use App\Service\CurrentWorkspaceProvider;
use App\Service\Court\EvidenceCourt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class CourtController extends AbstractController
{
    #[Route('/court', name: 'app_court', methods: ['GET'])]
    public function index(CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager, AiProviderConfiguration $ai): Response
    {
        $workspace = $workspaceProvider->get();

        return $this->render('court/index.html.twig', [
            'workspace' => $workspace,
            'cases' => $entityManager->getRepository(CourtCase::class)->findBy(['workspace' => $workspace], ['createdAt' => 'DESC'], 30),
            'prospects' => $entityManager->getRepository(Prospect::class)->findBy(['workspace' => $workspace], ['updatedAt' => 'DESC'], 12),
            'aiReady' => $ai->isReady($workspace),
        ]);
    }

    #[Route('/prospects/{id}/court', name: 'app_court_open', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function open(
        int $id,
        Request $request,
        CurrentWorkspaceProvider $workspaceProvider,
        EntityManagerInterface $entityManager,
        EvidenceCourt $court,
        MessageBusInterface $bus,
        RateLimiterFactory $aiSummaryLimiter,
    ): Response {
        $workspace = $workspaceProvider->get();
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$prospect instanceof Prospect) {
            throw $this->createNotFoundException('Prospect not found.');
        }
        if (!$this->isCsrfTokenValid('court_open_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the page and try again.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }
        if (!$court->isReady($prospect)) {
            $this->addFlash('error', 'Evidence Court needs a tested AI connection. Connect one in Integrations → AI first.');

            return $this->redirectToRoute('app_integrations_ai');
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$aiSummaryLimiter->create($user->getUserIdentifier())->consume(2)->isAccepted()) {
            $this->addFlash('error', 'You have reached the hourly AI limit. Try again later.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        $case = $court->open($prospect, $user);
        $bus->dispatch(new HearCourtCase((int) $case->getId()));

        return $this->redirectToRoute('app_court_show', ['id' => $case->getId()]);
    }

    #[Route('/court/{id}', name: 'app_court_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager): Response
    {
        $workspace = $workspaceProvider->get();
        $case = $entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$case instanceof CourtCase) {
            throw $this->createNotFoundException('Court case not found.');
        }

        return $this->render('court/show.html.twig', [
            'workspace' => $workspace,
            'case' => $case,
            'prospect' => $case->getProspect(),
            'transcript' => $case->getTranscript(),
        ]);
    }

    #[Route('/court/{id}/ruling', name: 'app_court_rule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rule(int $id, Request $request, CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager, EvidenceCourt $court): Response
    {
        $workspace = $workspaceProvider->get();
        $case = $entityManager->getRepository(CourtCase::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$case instanceof CourtCase) {
            throw $this->createNotFoundException('Court case not found.');
        }
        if (!$this->isCsrfTokenValid('court_rule_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the page and try again.');

            return $this->redirectToRoute('app_court_show', ['id' => $id]);
        }
        $judge = $this->getUser();
        if (!$judge instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $decision = (string) $request->request->get('decision', '');
        if (!in_array($decision, [ProspectDecision::DECISION_FINALIZED, ProspectDecision::DECISION_REJECTED, ProspectDecision::DECISION_NEEDS_REVIEW], true)) {
            $this->addFlash('error', 'Choose pursue, reject or needs more evidence.');

            return $this->redirectToRoute('app_court_show', ['id' => $id]);
        }

        try {
            $court->rule($case, $judge, $decision, (string) $request->request->get('reason', ''));
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_court_show', ['id' => $id]);
        }

        $this->addFlash('success', 'Ruling recorded. It is now part of your team memory, and future hearings on similar leads can cite it as precedent.');

        return $this->redirectToRoute('app_court_show', ['id' => $id]);
    }
}
