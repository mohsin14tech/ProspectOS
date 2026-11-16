<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'suppressed_contact')]
#[ORM\UniqueConstraint(name: 'uniq_suppressed_workspace_email', columns: ['workspace_id', 'email_address'])]
#[ORM\Index(name: 'idx_suppressed_workspace_created', columns: ['workspace_id', 'created_at'])]
class SuppressedContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $actor;

    #[ORM\Column(length: 180)]
    private string $emailAddress;

    #[ORM\Column(length: 500)]
    private string $reason;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Workspace $workspace, User $actor, string $emailAddress, string $reason)
    {
        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false || trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new \InvalidArgumentException('Enter a valid address and a suppression reason up to 500 characters.');
        }

        $this->workspace = $workspace;
        $this->actor = $actor;
        $this->emailAddress = mb_strtolower(trim($emailAddress));
        $this->reason = trim($reason);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getActor(): User { return $this->actor; }
    public function getEmailAddress(): string { return $this->emailAddress; }
    public function getReason(): string { return $this->reason; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
