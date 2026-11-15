<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'google_identity')]
#[ORM\UniqueConstraint(name: 'uniq_google_identity_subject', columns: ['subject'])]
#[ORM\UniqueConstraint(name: 'uniq_google_identity_user', columns: ['user_id'])]
class GoogleIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column]
    private \DateTimeImmutable $linkedAt;

    public function __construct(User $user, string $subject)
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->linkedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSubject(): string { return $this->subject; }
    public function getLinkedAt(): \DateTimeImmutable { return $this->linkedAt; }
}
