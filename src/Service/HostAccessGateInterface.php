<?php

declare(strict_types=1);

namespace App\Service;

interface HostAccessGateInterface
{
    /** Run one host inspection while enforcing shared cross-process pacing and concurrency limits. */
    public function run(string $host, callable $operation): mixed;
}
