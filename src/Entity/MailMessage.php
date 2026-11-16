<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_message')]
#[ORM\UniqueConstraint(name: 'uniq_gmail_message', columns: ['gmail_connection_id','remote_id'])]
#[ORM\UniqueConstraint(name: 'uniq_business_message', columns: ['business_connection_id','remote_id'])]
class MailMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?GmailConnection $gmailConnection = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?BusinessMailConnection $businessConnection = null;
    #[ORM\Column(length: 200)]
    private string $remoteId;
    #[ORM\Column(length: 200)]
    private string $threadId;
    #[ORM\Column(type: 'json')]
    private array $metadata;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;
    #[ORM\Column]
    private \DateTimeImmutable $syncedAt;
    public function __construct(GmailConnection|BusinessMailConnection $connection, array $row)
    {
        $this->workspace = $connection->getWorkspace();
        if ($connection instanceof GmailConnection) { $this->gmailConnection = $connection; } else { $this->businessConnection = $connection; }
        $this->remoteId = mb_substr((string) $row['id'], 0, 200);
        $this->threadId = mb_substr((string) $row['thread'], 0, 200);
        $this->update($row);
    }
    public function update(array $row): void
    {
        $this->metadata = [];
        foreach (['subject' => 500, 'sender' => 500, 'preview' => 500, 'date' => 100, 'messageId' => 500] as $key => $limit) {
            $this->metadata[$key] = mb_substr((string) ($row[$key] ?? ''), 0, $limit);
        }
        $this->syncedAt = new \DateTimeImmutable();
    }
    public function setBody(string $body): void { $this->body = mb_substr($body, 0, 20000); }
    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getGmailConnection(): ?GmailConnection { return $this->gmailConnection; }
    public function getBusinessConnection(): ?BusinessMailConnection { return $this->businessConnection; }
    public function getRemoteId(): string { return $this->remoteId; }
    public function getThreadId(): string { return $this->threadId; }
    public function getMetadata(): array { return $this->metadata; }
    public function getBody(): ?string { return $this->body; }
    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
}
