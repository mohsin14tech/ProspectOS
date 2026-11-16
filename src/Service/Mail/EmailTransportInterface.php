<?php
declare(strict_types=1);
namespace App\Service\Mail;
use App\Entity\BusinessMailConnection;
interface EmailTransportInterface
{
    public function send(BusinessMailConnection $connection, array $payload, string $idempotencyKey): string;
}
