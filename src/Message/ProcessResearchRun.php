<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessResearchRun
{
    public function __construct(public int $researchRunId)
    {
    }
}
