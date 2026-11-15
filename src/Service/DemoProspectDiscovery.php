<?php

declare(strict_types=1);

namespace App\Service;

/** Fixed fictional fixtures; no external business search or website fetching occurs. */
final class DemoProspectDiscovery implements DiscoveryProviderInterface
{
    /** @return list<array{slug: string, name: string, category: string, location: string, findingType: string, claim: string, excerpt: string, dimensions: array<string, int|null>}> */
    public function discover(string $objective = ''): array
    {
        return [
            [
                'slug' => 'juniper-table', 'name' => 'Juniper Table', 'category' => 'Independent restaurant', 'location' => 'Islamabad',
                'findingType' => 'ordering_signal', 'claim' => 'Synthetic fixture: online ordering was not detected in the sample profile.',
                'excerpt' => 'Fictional training record; no real business website was inspected.',
                'dimensions' => ['icpFit' => 82, 'opportunity' => 76, 'evidenceQuality' => 55, 'contactability' => null, 'intentRecency' => null],
            ],
            [
                'slug' => 'northstar-dental', 'name' => 'Northstar Dental', 'category' => 'Dental clinic', 'location' => 'Rawalpindi',
                'findingType' => 'mobile_performance_signal', 'claim' => 'Synthetic fixture: the sample profile includes a slow mobile-page signal.',
                'excerpt' => 'Fictional training record; the sample performance value is not a measured live website result.',
                'dimensions' => ['icpFit' => 72, 'opportunity' => 70, 'evidenceQuality' => 50, 'contactability' => null, 'intentRecency' => null],
            ],
            [
                'slug' => 'olive-and-grain', 'name' => 'Olive & Grain', 'category' => 'Cafe and bakery', 'location' => 'Islamabad',
                'findingType' => 'menu_signal', 'claim' => 'Synthetic fixture: the sample profile represents a downloadable-menu opportunity.',
                'excerpt' => 'Fictional training record; no real business website or menu was inspected.',
                'dimensions' => ['icpFit' => 78, 'opportunity' => 64, 'evidenceQuality' => 48, 'contactability' => null, 'intentRecency' => null],
            ],
        ];
    }

    public function sourceMode(): string
    {
        return 'demo';
    }
}
