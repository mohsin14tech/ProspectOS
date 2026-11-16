<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gmail_connection')]
#[ORM\UniqueConstraint(name: 'uniq_gmail_connection_workspace', columns: ['workspace_id'])]
class GmailConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\Column(length: 180)]
    private string $emailAddress;

    #[ORM\Column(type: 'text')]
    private string $encryptedRefreshToken;

    #[ORM\Column]
    private \DateTimeImmutable $connectedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $inboxAccess = false;

    public function __construct(Workspace $workspace, string $emailAddress, string $encryptedRefreshToken)
    {
        $this->workspace = $workspace;
        $this->emailAddress = mb_strtolower(trim($emailAddress));
        $this->encryptedRefreshToken = $encryptedRefreshToken;
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function replaceCredentials(string $emailAddress, string $encryptedRefreshToken): void
    {
        $this->emailAddress = mb_strtolower(trim($emailAddress));
        $this->encryptedRefreshToken = $encryptedRefreshToken;
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getEmailAddress(): string { return $this->emailAddress; }
    public function getEncryptedRefreshToken(): string { return $this->encryptedRefreshToken; }
    public function getConnectedAt(): \DateTimeImmutable { return $this->connectedAt; }
    public function hasInboxAccess(): bool { return $this->inboxAccess; }
    public function setInboxAccess(bool $value): void { $this->inboxAccess = $value; }
}
