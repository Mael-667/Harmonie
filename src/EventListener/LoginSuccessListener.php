<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;
use App\Controller\RegistrationController;

final class LoginSuccessListener
{

    //Lorsque l'utilisateur est sur le point de se connecter, j'intercepte l'evenement symfony puis j'injecte mon token de connexion dans les headers
    // l'implémentation de loginSuccessEvent se retrouve ici 
    // https://github.com/symfony/symfony/blob/8.0/src/Symfony/Component/Security/Http/Event/LoginSuccessEvent.php
    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        $response = $event->getResponse();

        if ($response === null) {
            return;
        }

        $token = $event->getUser()->getToken();

        $cookie = Cookie::create('tokenHarmonie')
            ->withValue($token)
            ->withExpires(new \DateTimeImmutable('+6 month'))
            ->withPath('/')
            ->withSecure(true)       // HTTPS uniquement
            ->withHttpOnly(true)     // Inaccessible via JS
            ->withSameSite('strict');

        $response->headers->setCookie($cookie);

        $event->setResponse($response);
    }
}
