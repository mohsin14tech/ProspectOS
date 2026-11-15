<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_membership')]
#[ORM\UniqueConstraint(name: 'uniq_membership_workspace_user', columns: ['workspace_id', 'user_id'])]
class WorkspaceMembership
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 24)]
    private string $role;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Workspace $workspace, User $user, string $role = self::ROLE_MEMBER)
    {
        if (!in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER], true)) {
            throw new \InvalidArgumentException('Unknown workspace membership role.');
        }

        $this->workspace = $workspace;
        $this->user = $user;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getUser(): User { return $this->user; }
    public function getRole(): string { return $this->role; }
}
