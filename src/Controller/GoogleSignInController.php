<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GoogleIdentity;
use App\Entity\User;
use App\Entity\WorkspaceMembership;
use App\Service\GoogleOidcClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleSignInController extends AbstractController
{
    #[Route('/login/google', name: 'app_google_signin_start', methods: ['GET'])]
    public function start(Request $request, GoogleOidcClient $google, RateLimiterFactory $googleOidcLimiter): Response
    {
        if (!$google->isConfigured()) {
            $this->addFlash('error', 'Google sign-in is not configured for this environment.');

            return $this->redirectToRoute('app_login');
        }
        if ($this->getUser() instanceof User) {
            $this->addFlash('error', 'Use Workspace settings to link a Google account after confirming your password.');

            return $this->redirectToRoute('app_workspace');
        }

        if (!$googleOidcLimiter->create('signin:'.($request->getClientIp() ?? 'unknown'))->consume()->isAccepted()) {
            $this->addFlash('error', 'Google sign-in is temporarily rate limited. Try again later.');

            return $this->redirectToRoute('app_login');
        }

        return $this->begin($request, $google, 'signin', null);
    }

    #[Route('/workspace/google/link', name: 'app_google_identity_link', methods: ['POST'])]
    public function link(
        Request $request,
        GoogleOidcClient $google,
        UserPasswordHasherInterface $passwordHasher,
        RateLimiterFactory $googleOidcLimiter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('link_google_identity', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Your session expired. Refresh workspace settings and try again.');

            return $this->redirectToRoute('app_workspace');
        }
        if (!$passwordHasher->isPasswordValid($user, (string) $request->request->get('password', ''))) {
            $this->addFlash('error', 'Password confirmation failed. Google was not linked.');

            return $this->redirectToRoute('app_workspace');
        }
        if (!$googleOidcLimiter->create('link:'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Google linking is temporarily rate limited. Try again later.');

            return $this->redirectToRoute('app_workspace');
        }
        if (!$google->isConfigured()) {
            $this->addFlash('error', 'Google sign-in is not configured for this environment.');

            return $this->redirectToRoute('app_workspace');
        }

        return $this->begin($request, $google, 'link', $user->getId());
    }

    #[Route('/login/google/callback', name: 'app_google_signin_callback', methods: ['GET'])]
    public function callback(
        Request $request,
        GoogleOidcClient $google,
        EntityManagerInterface $entityManager,
        Security $security,
        RateLimiterFactory $googleOidcLimiter,
    ): Response {
        $session = $request->getSession();
        $expectedState = (string) $session->get('google_oidc_state', '');
        $expectedNonce = (string) $session->get('google_oidc_nonce', '');
        $mode = (string) $session->get('google_oidc_mode', '');
        $linkUserId = $session->get('google_oidc_link_user_id');
        $errorRoute = 'link' === $mode ? 'app_workspace' : 'app_login';
        if (!$googleOidcLimiter->create('callback:'.($request->getClientIp() ?? 'unknown'))->consume()->isAccepted()) {
            $session->remove('google_oidc_state');
            $session->remove('google_oidc_nonce');
            $session->remove('google_oidc_mode');
            $session->remove('google_oidc_link_user_id');
            $this->addFlash('error', 'Google sign-in is temporarily rate limited. Try again later.');

            return $this->redirectToRoute($errorRoute);
        }
        $session->remove('google_oidc_state');
        $session->remove('google_oidc_nonce');
        $session->remove('google_oidc_mode');
        $session->remove('google_oidc_link_user_id');

        if ('' !== (string) $request->query->get('error', '')) {
            $this->addFlash('error', 'Google sign-in was cancelled or denied. You can try again.');

            return $this->redirectToRoute($errorRoute);
        }

        $receivedState = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        if ('' === $expectedState || '' === $expectedNonce || !hash_equals($expectedState, $receivedState) || '' === $code || !in_array($mode, ['signin', 'link'], true)) {
            $this->addFlash('error', 'Google sign-in could not be verified. Start again from this browser.');

            return $this->redirectToRoute($errorRoute);
        }

        try {
            $claims = $google->exchangeAndVerify($code);
        } catch (\Throwable) {
            $this->addFlash('error', 'Google sign-in could not be completed. Check the provider setup or try again later.');

            return $this->redirectToRoute($errorRoute);
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($expectedNonce, $claims['nonce'])) {
            $this->addFlash('error', 'Google sign-in could not be verified. Start again from this browser.');

            return $this->redirectToRoute($errorRoute);
        }

        $subject = $claims['sub'];
        $identity = $entityManager->getRepository(GoogleIdentity::class)->findOneBy(['subject' => $subject]);

        if ('link' === $mode) {
            $currentUser = $this->getUser();
            if (!$currentUser instanceof User || (int) $linkUserId !== $currentUser->getId()) {
                $this->addFlash('error', 'Your account session changed. Sign in and start linking again.');

                return $this->redirectToRoute($errorRoute);
            }
            if ($identity instanceof GoogleIdentity) {
                $this->addFlash('error', $identity->getUser()->getId() === $currentUser->getId()
                    ? 'This Google account is already linked.'
                    : 'That Google account is already linked to another ProspectOS account.');

                return $this->redirectToRoute('app_workspace');
            }

            try {
                $entityManager->persist(new GoogleIdentity($currentUser, $subject));
                $entityManager->flush();
            } catch (\Throwable) {
                $this->addFlash('error', 'That Google identity could not be linked. It may already belong to another account.');

                return $this->redirectToRoute('app_workspace');
            }
            $this->addFlash('success', 'Google account linked. Google sign-in will work only for this ProspectOS account.');

            return $this->redirectToRoute('app_workspace');
        }

        if (!$identity instanceof GoogleIdentity) {
            $this->addFlash('error', 'This Google account is not linked to an active ProspectOS account. Sign in with your existing email/password or ask the workspace owner for access.');

            return $this->redirectToRoute('app_login');
        }

        $user = $identity->getUser();
        if (!$entityManager->getRepository(WorkspaceMembership::class)->findOneBy(['user' => $user])) {
            $this->addFlash('error', 'This Google account is not linked to an active ProspectOS workspace. Ask the workspace owner for access.');

            return $this->redirectToRoute('app_login');
        }

        $session->migrate(true);
        $security->login($user, 'form_login');

        return $this->redirectToRoute('app_dashboard');
    }

    private function begin(Request $request, GoogleOidcClient $google, string $mode, ?int $linkUserId): Response
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $session = $request->getSession();
        $session->set('google_oidc_state', $state);
        $session->set('google_oidc_nonce', $nonce);
        $session->set('google_oidc_mode', $mode);
        if (null !== $linkUserId) {
            $session->set('google_oidc_link_user_id', $linkUserId);
        }

        try {
            return $this->redirect($google->authorizationUrl($state, $nonce));
        } catch (\Throwable) {
            $session->remove('google_oidc_state');
            $session->remove('google_oidc_nonce');
            $session->remove('google_oidc_mode');
            $session->remove('google_oidc_link_user_id');
            $this->addFlash('error', 'Google sign-in could not be started. Check the provider configuration.');

            return $this->redirectToRoute('app_login');
        }
    }
}
