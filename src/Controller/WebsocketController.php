<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
                "pseudo" => $user->getPseudo(),
                "userId" => $user->getId()
            ], 200);
        }

    }

    #[Route('/websocket/saveMessage', name: 'websocket_saveMessage')]
    public function saveMessage(Request $request, UserRepository $userRep, ChannelRepository $channelRepository, EntityManagerInterface $emi){
        $secret = $_ENV["IPC_SECRET"];
        if($secret != $request->headers->get("X-IPC-Secret")){
            throw new HttpException(403, 'Accès refusé');
        };

        // TODO: vérifier que l'utilisateur a bien acces au chan où il essaye d'envoyer le message
        $decodedRequest = json_decode($request->getContent());

        $userId = $decodedRequest->userId;
        $content = $decodedRequest->message;
        $channelId = $decodedRequest->channelId;
        $attachment = $decodedRequest->attachment;

        $message = new Message();

        $channelTarget = $channelRepository->findOneBy(["id" => trim($channelId)]);
        $user = $userRep->findOneBy(["id" => trim($userId)]);

        if($channelTarget == null){
            return new JsonResponse([
                "error" => "Channel inexistant"
            ], 400);
        }

        $message->setUser($user);
        $message->setContent($content);
        $message->setTimestamp(new DateTime('now'));
        $message->setAttachment($attachment);
        $message->setChannel($channelTarget);

        $emi->persist($message);
        $emi->flush();
    }
}
