<?php

declare(strict_types=1);

namespace App\Service;

interface PublicIpResolverInterface
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
