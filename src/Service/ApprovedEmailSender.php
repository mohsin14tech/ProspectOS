<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{EmailAction, BusinessMailConnection, SuppressedContact, User, Workspace, WorkspaceMembership};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use App\Service\Mail\EmailTransportInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ApprovedEmailSender
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EmailTransportInterface $sender,
        #[Autowire(env: 'bool:OUTBOUND_EMAIL_PAUSED')] private readonly bool $globallyPaused = true) {}

    public function send(int $id, Workspace $workspace, User $actor): void
    {
        if ($this->globallyPaused) { throw new \DomainException('Outbound email is paused by the server operator.'); }
        // Commit the claim before contacting SMTP. Crashes/timeouts never cause an automatic resend.
        $this->em->wrapInTransaction(function () use ($id, $workspace, $actor, &$action, &$connection): void {
            $membership = $this->em->getRepository(WorkspaceMembership::class)->findOneBy(['workspace' => $workspace, 'user' => $actor]);
            if (!$membership instanceof WorkspaceMembership || !in_array($membership->getRole(), ['owner', 'admin'], true)) {
                throw new \DomainException('Only a workspace administrator can send an approved test.');
            }
            $connection = $this->em->getRepository(BusinessMailConnection::class)->findOneBy(['workspace' => $workspace]);
            $action = $this->em->getRepository(EmailAction::class)->findOneBy(['id' => $id, 'workspace' => $workspace]);
            if (!$connection instanceof BusinessMailConnection || !$action instanceof EmailAction) { throw new \DomainException('Email action not found.'); }
            $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($action, LockMode::PESSIMISTIC_WRITE);
            $recipient = $action->getPayload()['to'][0];
            if ($recipient !== $connection->getTestRecipient() || $this->em->getRepository(SuppressedContact::class)->findOneBy(['workspace' => $workspace, 'emailAddress' => $recipient])) {
                throw new \DomainException('The recipient is suppressed or is not the configured test address.');
            }
            $recent = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_action WHERE workspace_id = ? AND attempted_at >= ?', [$workspace->getId(), (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]);
            if ((int) $recent >= 5) { throw new \DomainException('The workspace limit is five test attempts per hour.'); }
            if ($connection->getTestedAt() === null) { throw new \DomainException('Test the IMAP and SMTP connection first.'); }
            $action->beginAttempt($connection, new \DateTimeImmutable());
        });
        try {
            $action->finish($this->sender->send($connection, $action->getPayload(), $action->getIdempotencyKey()));
        } catch (\Throwable) {
            $action->finish(null);
        }
        $this->em->flush();
    }
}
