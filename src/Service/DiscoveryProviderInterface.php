<?php

declare(strict_types=1);

namespace App\Service;

interface DiscoveryProviderInterface
{
    /** @return list<array{slug: string, name: string, category: string, location: string, findingType: string, claim: string, excerpt: string, dimensions: array<string, int|null>, sourceUrl?: string, sourceTitle?: string, retrievalMethod?: string, verificationState?: string, confidence?: float}> */
    public function discover(string $objective): array;

    public function sourceMode(): string;
}
