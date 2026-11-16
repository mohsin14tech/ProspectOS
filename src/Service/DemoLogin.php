<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\WorkspaceMembership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Opt-in local demo account for people evaluating a fresh clone.
 *
 * It only exists when DEMO_LOGIN_PASSWORD is set and the app is not running
 * in the prod environment. The account owns the synthetic "demo" workspace,
 * never a real one, and does not count as the first real owner.
 */
final class DemoLogin
{
    public const EMAIL = 'demo@prospectos.test';
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoWorkspaceProvider $workspaceProvider,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(env: 'default::string:DEMO_LOGIN_PASSWORD')] private readonly ?string $password,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
    }

    public function isEnabled(): bool
    {
        return 'prod' !== $this->environment
            && null !== $this->password
            && mb_strlen(trim($this->password)) >= self::MIN_PASSWORD_LENGTH;
    }

    public function password(): ?string
    {
        return $this->isEnabled() ? trim((string) $this->password) : null;
    }

    /** Creates or refreshes the demo owner. Returns false when disabled. */
    public function provision(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $users = $this->entityManager->getRepository(User::class);
        $user = $users->findOneBy(['email' => self::EMAIL]);
        if (!$user instanceof User) {
            $user = new User(self::EMAIL);
            $this->entityManager->persist($user);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $this->password()));

        $workspace = $this->workspaceProvider->get();
        $membership = $this->entityManager->getRepository(WorkspaceMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof WorkspaceMembership) {
            $this->entityManager->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        }
        $this->entityManager->flush();

        return true;
    }

    /** Number of accounts that are not the local demo account. */
    public function countRealUsers(): int
    {
        return (int) $this->entityManager->createQuery('SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.email <> :demo')
            ->setParameter('demo', self::EMAIL)
            ->getSingleScalarResult();
    }
}
