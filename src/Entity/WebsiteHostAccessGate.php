<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'website_host_access_gate')]
#[ORM\Index(name: 'idx_website_host_access_last_seen', columns: ['last_seen_at'])]
class WebsiteHostAccessGate
{
    #[ORM\Id]
    #[ORM\Column(length: 253)]
    private string $host;

    #[ORM\Column]
    private \DateTimeImmutable $nextAllowedAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;
}
