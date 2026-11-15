<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

final class AuthenticationTest extends WebTestCase
{
    private AbstractBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('TRUNCATE TABLE workspace_membership, app_user, workspace RESTART IDENTITY CASCADE');
    }

    public function testFirstOwnerSetupLoginWorkspaceAccessAndLogout(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Welcome back.');

        $crawler = $this->client->request('GET', '/setup');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Create owner workspace')->form([
            'workspace_name' => 'Harbor Research',
            'email' => 'Owner@Example.test',
            'password' => 'correct horse battery staple',
            'owner_token' => 'test-only-bootstrap-key-not-for-real-use',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/login');

        $crawler = $this->client->request('GET', '/setup');
        self::assertResponseRedirects('/login');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'owner@example.test',
            '_password' => 'correct horse battery staple',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profile-link', 'owner@example.test');

        $this->client->request('GET', '/workspace');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Harbor Research');
        $crawler = $this->client->request('GET', '/research');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/integrations');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Integrations');

        $form = $crawler->selectButton('Sign out')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserWithoutMembershipCannotReadWorkspace(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User('orphan@example.test');
        $user->setPassword('not-used-in-this-test');
        $entityManager->persist($user);
        $entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerSetupRejectsAnInvalidProvisioningToken(): void
    {
        $crawler = $this->client->request('GET', '/setup');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Create owner workspace')->form([
            'workspace_name' => 'Harbor Research',
            'email' => 'owner@example.test',
            'password' => 'correct horse battery staple',
            'owner_token' => 'wrong-token',
        ]);
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'Owner setup could not be verified');
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->count([]));
    }
}
