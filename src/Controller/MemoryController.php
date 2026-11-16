<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use App\Entity\User;
use App\Service\CurrentWorkspaceProvider;
use App\Service\ProspectMemory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemoryController extends AbstractController
{
    #[Route('/memory', name: 'app_memory', methods: ['GET'])]
    public function index(CurrentWorkspaceProvider $workspaceProvider, ProspectMemory $memory, EntityManagerInterface $entityManager): Response
    {
        $workspace = $workspaceProvider->get();
        $prospects = $entityManager->getRepository(Prospect::class)->findBy(
            ['workspace' => $workspace],
            ['updatedAt' => 'DESC'],
            100,
        );

        return $this->render('memory/index.html.twig', [
            'workspace' => $workspace,
            'counts' => $memory->counts($workspace),
            'prospects' => $prospects,
            'decisions' => $entityManager->getRepository(ProspectDecision::class)->findBy(
                ['workspace' => $workspace],
                ['createdAt' => 'DESC'],
                30,
            ),
        ]);
    }

    #[Route('/prospects/{id}/decision', name: 'app_prospect_decide', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function decide(
        int $id,
        Request $request,
        CurrentWorkspaceProvider $workspaceProvider,
        EntityManagerInterface $entityManager,
    ): Response {
        $workspace = $workspaceProvider->get();
        $prospect = $entityManager->getRepository(Prospect::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
        if (!$prospect instanceof Prospect) {
            throw $this->createNotFoundException('Prospect not found.');
        }
        if (!$this->isCsrfTokenValid('decide_prospect_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh the report and try again.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        $decision = (string) $request->request->get('decision', '');
        $reason = trim((string) $request->request->get('reason', ''));
        if (!in_array($decision, [
            ProspectDecision::DECISION_NEEDS_REVIEW,
            ProspectDecision::DECISION_FINALIZED,
            ProspectDecision::DECISION_REJECTED,
            ProspectDecision::DECISION_ARCHIVED,
        ], true) || mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
            $this->addFlash('error', 'Choose a valid decision and add a reason between 3 and 1000 characters.');

            return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $prospect->decide($decision);
        $entityManager->persist(new ProspectDecision($workspace, $prospect, $actor, $decision, $reason));
        $entityManager->flush();
        $this->addFlash('success', 'Decision saved in your workspace memory. Future research can use this reviewed example.');

        return $this->redirectToRoute('app_prospect_show', ['id' => $id]);
    }
}
