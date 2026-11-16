<?php
declare(strict_types=1);
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class MailPrivacyHeaders implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array { return [KernelEvents::RESPONSE => ['onResponse', -2000]]; }
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !preg_match('#^/(inbox|outreach)(/|$)#', $event->getRequest()->getPathInfo())) { return; }
        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'private, no-store, max-age=0');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
    }
}
