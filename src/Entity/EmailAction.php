<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** The provider payload has no edit method. Changes require a new action and approval. */
#[ORM\Entity]
#[ORM\Table(name: 'email_action')]
class EmailAction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?BusinessMailConnection $connection;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $actor;
    #[ORM\Column(type: 'json')]
    private array $payload;
    #[ORM\Column(length: 64)]
    private string $payloadHash;
    #[ORM\Column(length: 64, unique: true)]
    private string $idempotencyKey;
    #[ORM\Column]
    private int $connectionRevision;
    #[ORM\Column(length: 24)]
    private string $status = 'pending';
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedUntil = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $approvedBy = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $attemptedAt = null;
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $providerId = null;

    public function __construct(BusinessMailConnection $connection, User $actor, string $subject, string $body)
    {
        if (trim($subject) === '' || preg_match('/[\r\n]/', $subject) || mb_strlen($subject) > 200 || trim($body) === '' || mb_strlen($body) > 10000) {
            throw new \InvalidArgumentException('Enter a single-line subject up to 200 characters and a body up to 10000 characters.');
        }
        $this->workspace = $connection->getWorkspace();
        $this->connection = $connection;
        $this->actor = $actor;
        $this->connectionRevision = $connection->getRevision();
        $this->payload = ['from' => $connection->getSender(), 'to' => [$connection->getTestRecipient()], 'subject' => '[ProspectOS test] '.$subject, 'text' => $body];
        $this->payloadHash = hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR));
        $this->idempotencyKey = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
    }
    public function approve(User $actor, string $hash, \DateTimeImmutable $now): void
    {
        if ($this->status !== 'pending' || !hash_equals($this->payloadHash, $hash)) {
            throw new \DomainException('This approval no longer matches a pending message.');
        }
        $this->approvedBy = $actor;
        $this->approvedUntil = $now->modify('+15 minutes');
        $this->status = 'approved';
    }
    public function reject(): void
    {
        if (!in_array($this->status, ['pending', 'approved'], true)) {
            throw new \DomainException('An attempted message cannot be rejected.');
        }
        $this->status = 'rejected';
    }
    public function beginAttempt(BusinessMailConnection $connection, \DateTimeImmutable $now): void
    {
        if ($this->status !== 'approved' || $this->approvedUntil === null || $this->approvedUntil <= $now || $this->approvedBy === null
            || $this->connectionRevision !== $connection->getRevision() || $connection->isPaused()
            || $this->connection === null || $this->connection->getId() !== $connection->getId()
            || $this->workspace->getId() !== $connection->getWorkspace()->getId()
            || !hash_equals($this->payloadHash, hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR)))) {
            throw new \DomainException('A current approval and unchanged, unpaused connection are required.');
        }
        $this->status = 'attempting';
        $this->attemptedAt = $now;
    }
    public function finish(?string $providerId): void
    {
        if ($this->status !== 'attempting') { throw new \LogicException('No send attempt is active.'); }
        $this->providerId = $providerId;
        $this->status = $providerId === null ? 'uncertain' : 'accepted';
    }
    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getActor(): User { return $this->actor; }
    public function getPayload(): array { return $this->payload; }
    public function getPayloadHash(): string { return $this->payloadHash; }
    public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getApprovedUntil(): ?\DateTimeImmutable { return $this->approvedUntil; }
    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function getProviderId(): ?string { return $this->providerId; }
    public function getAttemptedAt(): ?\DateTimeImmutable { return $this->attemptedAt; }
}
