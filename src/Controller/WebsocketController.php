<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;


final class WebsocketController extends AbstractController
{
    #[Route('/websocket/login', name: 'websocket_login')]
    public function checkCred(Request $request, UserRepository $userRep){
        
        $secret = $_ENV["IPC_SECRET"];
        if($secret != $request->headers->get("X-IPC-Secret")){
            throw new HttpException(403, 'Accès refusé');
        };

        $decodedRequest = json_decode($request->getContent());
        $userToken = $decodedRequest->token;

        $user = $userRep->findOneBy(["token" => trim($userToken)]);
        
        if($user == null){
            return new JsonResponse([
                "error" => "Bad request"
            ], 400);
        } else {
            return new JsonResponse([
                "pseudo" => $user->getPseudo()
            ], 200);
        }

    }
}
