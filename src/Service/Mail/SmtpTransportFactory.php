<?php
declare(strict_types=1);
namespace App\Service\Mail;
use App\Entity\BusinessMailConnection;
use App\Service\{PublicUrlPolicy, SecretVault};
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

final class SmtpTransportFactory
{
    public function __construct(private readonly PublicUrlPolicy $policy, private readonly SecretVault $vault) {}
    public function create(BusinessMailConnection $connection): EsmtpTransport
    {
        $settings = $connection->getSettings();
        $target = $this->policy->validateAndPin('https://'.$settings['smtpHost'].'/');
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
        $transport = new AuthenticatedSmtpTransport($ip, $settings['smtpPort'], $settings['smtpPort'] === 465);
        $transport->setRequireTls(true);
        $transport->setUsername($settings['username']);
        $transport->setPassword($this->vault->decrypt($connection->getEncryptedPassword(), 'business-mail'));
        $transport->getStream()->setTimeout(5);
        $transport->getStream()->setStreamOptions(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $target['host'], 'SNI_enabled' => true, 'allow_self_signed' => false]]);
        return $transport;
    }
}
