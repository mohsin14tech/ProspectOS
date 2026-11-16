<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CourtCase;
use App\Message\HearCourtCase;
use App\Service\Court\EvidenceCourt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class HearCourtCaseHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EvidenceCourt $court,
    ) {
    }

    public function __invoke(HearCourtCase $message): void
    {
        $case = $this->entityManager->getRepository(CourtCase::class)->find($message->courtCaseId);
        if ($case instanceof CourtCase) {
            $this->court->hear($case);
        }
    }
}
