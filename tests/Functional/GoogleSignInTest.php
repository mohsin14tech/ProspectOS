<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GoogleIdentity;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMembership;
use App\Service\GoogleOidcClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GoogleSignInTest extends WebTestCase
{
    private AbstractBrowser $client;
    private string $expectedNonce = '';

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['REMOTE_ADDR' => sprintf('192.0.2.%d', random_int(1, 254))]);
        $this->client->disableReboot();
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            'TRUNCATE TABLE workspace_membership, google_identity, app_user, workspace RESTART IDENTITY CASCADE',
        );
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testLinkedGoogleSubjectSignsInExistingWorkspaceMember(): void
    {
        $user = $this->createMember('linked@example.test');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new GoogleIdentity($user, 'google-sub-123'));
        $em->flush();
        $this->installGoogleClient(['sub' => 'google-sub-123', 'email' => 'other@example.test', 'email_verified' => true]);

        $this->startAndCompleteGoogleSignIn();
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profile-link', 'linked@example.test');
    }

    public function testMatchingEmailWithoutLinkedSubjectCannotSignIn(): void
    {
        $this->createMember('same@example.test');
        $this->installGoogleClient(['sub' => 'unlinked-sub-456', 'email' => 'same@example.test', 'email_verified' => true]);

        $this->startAndCompleteGoogleSignIn();

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(GoogleIdentity::class)->count([]));
    }

    public function testMissingStateCannotCallTheTokenExchange(): void
    {
        $google = $this->createMock(GoogleOidcClient::class);
        $google->expects(self::never())->method('exchangeAndVerify');
        static::getContainer()->set(GoogleOidcClient::class, $google);

        $this->client->request('GET', '/login/google/callback?state=forged&code=single-use-code');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Google sign-in could not be verified');
    }

    public function testGoogleAccessDeniedReturnsARecoverableLoginState(): void
    {
        $google = $this->createMock(GoogleOidcClient::class);
        $google->method('isConfigured')->willReturn(true);
        $google->method('authorizationUrl')->willReturn('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $google->expects(self::never())->method('exchangeAndVerify');
        static::getContainer()->set(GoogleOidcClient::class, $google);
        $this->client->request('GET', '/login/google');
        self::assertResponseRedirects('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $session = $this->client->getRequest()->getSession();
        $state = $session->get('google_oidc_state');

        $this->client->request('GET', '/login/google/callback?error=access_denied&state='.rawurlencode((string) $state));
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'cancelled or denied');
    }

    public function testPasswordConfirmedLinkUsesGoogleSubjectAndRejectsConflictingIdentity(): void
    {
        $user = $this->createMember('owner@example.test', 'correct horse battery staple');
        $this->client->loginUser($user);
        $this->installGoogleClient(['sub' => 'new-google-sub', 'email' => 'owner@example.test', 'email_verified' => true]);
        $crawler = $this->client->request('GET', '/workspace');
        $form = $crawler->selectButton('Continue to Google')->form(['password' => 'wrong password']);
        $this->client->submit($form);
        self::assertResponseRedirects('/workspace');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Password confirmation failed');

        $crawler = $this->client->request('GET', '/workspace');
        $form = $crawler->selectButton('Continue to Google')->form(['password' => 'correct horse battery staple']);
        $this->client->submit($form);
        self::assertResponseRedirects('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $this->completeGoogleCallback('link');
        self::assertResponseRedirects('/workspace');

        $identity = static::getContainer()->get(EntityManagerInterface::class)->getRepository(GoogleIdentity::class)->findOneBy(['subject' => 'new-google-sub']);
        self::assertInstanceOf(GoogleIdentity::class, $identity);
        self::assertSame($user->getId(), $identity->getUser()->getId());
    }

    public function testGoogleSubjectAlreadyLinkedToAnotherUserIsRejected(): void
    {
        $user = $this->createMember('first@example.test', 'correct horse battery staple');
        $otherUser = $this->createMember('second@example.test');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new GoogleIdentity($otherUser, 'conflicting-google-sub'));
        $em->flush();
        $this->client->loginUser($user);
        $this->installGoogleClient(['sub' => 'conflicting-google-sub', 'email' => 'second@example.test', 'email_verified' => true]);

        $crawler = $this->client->request('GET', '/workspace');
        $form = $crawler->selectButton('Continue to Google')->form(['password' => 'correct horse battery staple']);
        $this->client->submit($form);
        self::assertResponseRedirects('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $this->completeGoogleCallback('link');
        self::assertResponseRedirects('/workspace');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'already linked to another ProspectOS account');

        $identity = $em->getRepository(GoogleIdentity::class)->findOneBy(['subject' => 'conflicting-google-sub']);
        self::assertInstanceOf(GoogleIdentity::class, $identity);
        self::assertSame($otherUser->getId(), $identity->getUser()->getId());
    }

    /** @param array<string, mixed> $claims */
    private function installGoogleClient(array $claims): void
    {
        $google = $this->createMock(GoogleOidcClient::class);
        $google->method('isConfigured')->willReturn(true);
        $google->method('authorizationUrl')->willReturn('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $google->method('exchangeAndVerify')->willReturnCallback(function (string $code) use ($claims): array {
            self::assertSame('single-use-code', $code);
            $claims['nonce'] = $this->expectedNonce;

            return $claims;
        });
        static::getContainer()->set(GoogleOidcClient::class, $google);
    }

    private function startAndCompleteGoogleSignIn(): void
    {
        $this->client->request('GET', '/login/google');
        self::assertResponseRedirects('https://accounts.google.com/o/oauth2/v2/auth?test=1');
        $this->completeGoogleCallback('signin');
    }

    private function completeGoogleCallback(string $expectedMode): void
    {
        $session = $this->client->getRequest()->getSession();
        $state = $session->get('google_oidc_state');
        self::assertNotEmpty($state);
        self::assertSame($expectedMode, $session->get('google_oidc_mode'));
        $this->expectedNonce = (string) $session->get('google_oidc_nonce');
        $this->client->request('GET', '/login/google/callback?state='.rawurlencode((string) $state).'&code=single-use-code');
    }

    private function createMember(string $email, string $password = 'unused-test-password'): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User($email);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        $workspace = new Workspace('Test workspace', 'test-'.bin2hex(random_bytes(4)));
        $em->persist($user);
        $em->persist($workspace);
        $em->persist(new WorkspaceMembership($workspace, $user, WorkspaceMembership::ROLE_OWNER));
        $em->flush();

        return $user;
    }
}
