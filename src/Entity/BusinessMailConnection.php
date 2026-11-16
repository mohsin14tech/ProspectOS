<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'business_mail_connection')]
#[ORM\UniqueConstraint(name: 'uniq_business_mail_workspace', columns: ['workspace_id'])]
class BusinessMailConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;
    #[ORM\Column(type: 'json')]
    private array $settings;
    #[ORM\Column(type: 'text')]
    private string $encryptedPassword;
    #[ORM\Column]
    private int $revision = 0;
    #[ORM\Column]
    private bool $paused = true;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $testedAt = null;

    public function __construct(Workspace $workspace, array $settings, string $encryptedPassword)
    {
        $this->workspace = $workspace;
        $this->configure($settings, $encryptedPassword);
    }
    public function configure(array $settings, string $encryptedPassword): void
    {
        foreach (['sender', 'testRecipient'] as $key) {
            if (!is_string($settings[$key] ?? null) || strlen($settings[$key]) > 180 || !filter_var($settings[$key], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Enter a valid mailbox and test recipient address.');
            }
        }
        foreach (['imapHost', 'smtpHost'] as $key) {
            if (!is_string($settings[$key] ?? null) || !preg_match('/^(?=.{1,253}$)[a-zA-Z0-9.-]+$/', $settings[$key])) {
                throw new \InvalidArgumentException('Enter server hostnames without a URL or port.');
            }
        }
        if (!in_array($settings['smtpPort'] ?? null, [465, 587], true) || trim($settings['username'] ?? '') === '' || strlen($settings['username']) > 180) {
            throw new \InvalidArgumentException('Use SMTP port 465 or 587 and a mailbox username. IMAP uses TLS on port 993.');
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $settings['username']) || $encryptedPassword === '') {
            throw new \InvalidArgumentException('Enter valid mailbox credentials.');
        }
        $this->settings = array_intersect_key($settings, array_flip(['sender', 'testRecipient', 'imapHost', 'smtpHost', 'smtpPort', 'username']));
        $this->encryptedPassword = $encryptedPassword;
        ++$this->revision;
        $this->testedAt = null; $this->paused = true;
    }
    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function getSettings(): array { return $this->settings; }
    public function getEncryptedPassword(): string { return $this->encryptedPassword; }
    public function getSender(): string { return strtolower($this->settings['sender']); }
    public function getTestRecipient(): string { return strtolower($this->settings['testRecipient']); }
    public function getRevision(): int { return $this->revision; }
    public function isPaused(): bool { return $this->paused; }
    public function setPaused(bool $value): void { $this->paused = $value; }
    public function getTestedAt(): ?\DateTimeImmutable { return $this->testedAt; }
    public function markTested(): void { $this->testedAt = new \DateTimeImmutable(); }
    public function invalidateTest(): void { $this->testedAt = null; $this->paused = true; }
}
