<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\GoogleIdentity;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Service\CurrentWorkspaceProvider;
use App\Service\GoogleOidcClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, EntityManagerInterface $entityManager, GoogleOidcClient $googleOidc, #[Autowire(env: 'string:APP_OWNER_SETUP_TOKEN')] string $ownerSetupToken): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'lastUsername' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'hasUsers' => $entityManager->getRepository(User::class)->count([]) > 0,
            'ownerSetupAvailable' => '' !== trim($ownerSetupToken),
            'googleSignInConfigured' => $googleOidc->isConfigured(),
        ]);
    }

    #[Route('/setup', name: 'app_setup', methods: ['GET', 'POST'])]
    public function setup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        #[Autowire(env: 'string:APP_OWNER_SETUP_TOKEN')] string $ownerSetupToken,
        RateLimiterFactory $ownerSetupLimiter,
    ): Response {
        if ($entityManager->getRepository(User::class)->count([]) > 0 || '' === trim($ownerSetupToken)) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];
        $email = trim((string) $request->request->get('email', ''));
        $workspaceName = trim((string) $request->request->get('workspace_name', ''));
        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $submittedSetupToken = (string) $request->request->get('owner_token', '');
            $setupLimit = $ownerSetupLimiter->create($request->getClientIp() ?? 'unknown')->consume();

            if (!$this->isCsrfTokenValid('first_owner_setup', (string) $request->request->get('_token', ''))) {
                $errors[] = 'Your setup session expired. Refresh this page and try again.';
            }
            if (!hash_equals($ownerSetupToken, $submittedSetupToken)) {
                $errors[] = 'Owner setup could not be verified. Check the one-time setup token with your administrator.';
            }
            if (!$setupLimit->isAccepted()) {
                $errors[] = 'Owner setup has reached its attempt limit. Try again later.';
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 180) {
                $errors[] = 'Enter a valid email address.';
            }
            if (mb_strlen($workspaceName) < 2 || mb_strlen($workspaceName) > 120) {
                $errors[] = 'Workspace name must be 2–120 characters.';
            }
            if (strlen($password) < 12 || strlen($password) > 200) {
                $errors[] = 'Password must be between 12 and 200 characters.';
            }

            if ($errors === []) {
                $email = mb_strtolower($email);
                $owner = new User($email);
                $owner->setPassword($passwordHasher->hashPassword($owner, $password));
                $workspace = new Workspace($workspaceName, 'workspace-'.bin2hex(random_bytes(8)));
                $membership = new WorkspaceMembership($workspace, $owner, WorkspaceMembership::ROLE_OWNER);

                $created = $entityManager->getConnection()->transactional(function (Connection $connection) use ($entityManager, $owner, $workspace, $membership): bool {
                    // Serialize first-owner setup so concurrent first registrations cannot both become owners.
                    $connection->executeQuery('SELECT pg_advisory_xact_lock(148903207)');
                    if ($entityManager->getRepository(User::class)->count([]) > 0) {
                        return false;
                    }

                    $entityManager->persist($owner);
                    $entityManager->persist($workspace);
                    $entityManager->persist($membership);
                    $entityManager->flush();

                    return true;
                });

                if ($created) {
                    $this->addFlash('success', 'Workspace owner created. Sign in to continue.');
                }

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/setup.html.twig', [
            'email' => $email,
            'workspaceName' => $workspaceName,
            'errors' => $errors,
        ]);
    }

    #[Route('/workspace', name: 'app_workspace', methods: ['GET'])]
    public function workspace(CurrentWorkspaceProvider $workspaceProvider, EntityManagerInterface $entityManager, GoogleOidcClient $googleOidc): Response
    {
        $user = $this->getUser();

        return $this->render('security/workspace.html.twig', [
            'workspace' => $workspaceProvider->get(),
            'googleIdentity' => $entityManager->getRepository(GoogleIdentity::class)->findOneBy(['user' => $user]),
            'googleConfigured' => $googleOidc->isConfigured(),
        ]);
    }
}
