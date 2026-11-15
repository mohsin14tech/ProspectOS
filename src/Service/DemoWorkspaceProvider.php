<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class DemoWorkspaceProvider
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(): Workspace
    {
        $repository = $this->entityManager->getRepository(Workspace::class);
        $workspace = $repository->findOneBy(['slug' => 'demo']);
        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $workspace = new Workspace('14 Technologies Demo', 'demo');
        $this->entityManager->persist($workspace);
        $this->entityManager->flush();

        return $workspace;
    }
}
