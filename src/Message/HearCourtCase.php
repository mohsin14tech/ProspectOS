<?php

declare(strict_types=1);

namespace App\Message;

final readonly class HearCourtCase
{
    public function __construct(public int $courtCaseId)
    {
    }
}
