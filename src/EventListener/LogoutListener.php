<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListener
{
    #[AsEventListener]
    public function onLogoutEvent(LogoutEvent $event): void
    {
        $response = $event->getResponse();

        $response->headers->clearCookie("token");

        $event->setResponse($response);
    }
}
