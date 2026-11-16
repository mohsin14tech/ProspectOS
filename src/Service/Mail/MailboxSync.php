<?php
declare(strict_types=1);
namespace App\Service\Mail;

use App\Entity\{BusinessMailConnection, GmailConnection, MailMessage, Workspace};
use App\Service\GmailTokenVault;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class MailboxSync
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly BusinessMailboxInterface $business,
        private readonly GmailGatewayInterface $gmail, private readonly GmailTokenVault $vault) {}

    public function sync(Workspace $workspace, string $provider): int
    {
        return $this->em->wrapInTransaction(function () use ($workspace, $provider): int {
            $connection = $this->connection($workspace, $provider);
            $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
            $rows = $connection instanceof GmailConnection
                ? $this->gmail->recent($this->vault->decrypt($connection->getEncryptedRefreshToken()))
                : $this->business->recent($connection);
            $field = $connection instanceof GmailConnection ? 'gmailConnection' : 'businessConnection';
            foreach (array_slice($rows, 0, 10) as $row) {
                $message = $this->em->getRepository(MailMessage::class)->findOneBy([$field => $connection, 'remoteId' => $row['id']]);
                if ($message instanceof MailMessage) { $message->update($row); }
                else { $this->em->persist(new MailMessage($connection, $row)); }
            }
            $this->em->flush();
            // A bounded cache, not a mirror or archive of the provider mailbox.
            $older = $this->em->getRepository(MailMessage::class)->findBy([$field => $connection], ['syncedAt' => 'DESC', 'id' => 'DESC'], null, 100);
            foreach ($older as $message) { $this->em->remove($message); }
            return min(count($rows), 10);
        });
    }

    public function fetchBody(MailMessage $message): void
    {
        $connection = $message->getGmailConnection() ?? $message->getBusinessConnection();
        if ($connection instanceof GmailConnection) {
            if (!$connection->hasInboxAccess()) { throw new \DomainException('Reconnect Gmail to grant inbox access.'); }
            $body = $this->gmail->body($this->vault->decrypt($connection->getEncryptedRefreshToken()), $message->getRemoteId());
        } elseif ($connection instanceof BusinessMailConnection && $connection->getTestedAt() !== null) {
            $body = $this->business->body($connection, $message->getRemoteId());
        } else { throw new \DomainException('Test or reconnect this mailbox first.'); }
        $message->setBody($body);
        $this->em->flush();
    }

    private function connection(Workspace $workspace, string $provider): GmailConnection|BusinessMailConnection
    {
        $class = match ($provider) { 'gmail' => GmailConnection::class, 'business' => BusinessMailConnection::class, default => throw new \InvalidArgumentException('Choose Gmail or IMAP.') };
        $connection = $this->em->getRepository($class)->findOneBy(['workspace' => $workspace]);
        if (!$connection || ($connection instanceof GmailConnection && !$connection->hasInboxAccess()) || ($connection instanceof BusinessMailConnection && $connection->getTestedAt() === null)) {
            throw new \DomainException('Connect and test this mailbox before syncing.');
        }
        return $connection;
    }
}
