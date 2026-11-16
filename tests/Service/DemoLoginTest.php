<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DemoLogin;
use App\Service\DemoWorkspaceProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoLoginTest extends TestCase
{
    public function testDemoLoginIsNeverAvailableInProduction(): void
    {
        self::assertFalse($this->login('prospectos-demo', 'prod')->isEnabled());
        self::assertNull($this->login('prospectos-demo', 'prod')->password());
        self::assertFalse($this->login('prospectos-demo', 'prod')->provision());
    }

    public function testDemoLoginNeedsAnExplicitPasswordOfReasonableLength(): void
    {
        self::assertFalse($this->login(null, 'dev')->isEnabled());
        self::assertFalse($this->login('', 'dev')->isEnabled());
        self::assertFalse($this->login('short', 'dev')->isEnabled());
        self::assertTrue($this->login('prospectos-demo', 'dev')->isEnabled());
        self::assertSame('prospectos-demo', $this->login(' prospectos-demo ', 'dev')->password());
    }

    private function login(?string $password, string $environment): DemoLogin
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new DemoLogin($entityManager, new DemoWorkspaceProvider($entityManager), $this->createStub(UserPasswordHasherInterface::class), $password, $environment);
    }
}
