<?php
declare(strict_types=1);
namespace App\Service\Mail;
use App\Entity\BusinessMailConnection;

interface BusinessMailboxInterface
{
    public function test(BusinessMailConnection $connection): void;
    public function recent(BusinessMailConnection $connection): array;
    public function body(BusinessMailConnection $connection, string $reference): string;
}
