<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

final class CurrentWorkspaceProvider
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(): Workspace
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('An authenticated workspace member is required.');
        }

        $membership = $this->entityManager->getRepository(WorkspaceMembership::class)->findOneBy(['user' => $user], ['id' => 'ASC']);
        if (!$membership instanceof WorkspaceMembership) {
            throw new AccessDeniedException('The signed-in user has no workspace membership.');
        }

        return $membership->getWorkspace();
    }
}
