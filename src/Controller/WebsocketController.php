<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\PermissionManager;
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
                "userId" => $user->getId(),
                "userAvatar" => $user->getAvatarUrl()
            ], 200);
        }

    }

    #[Route('/websocket/saveMessage', name: 'websocket_saveMessage')]
    public function saveMessage(Request $request, PermissionManager $permissionManager, UserRepository $userRep, EntityManagerInterface $emi){
        // TODO: découpler la vérification de l'insertion des données, faire 2 request ptet
        $secret = $_ENV["IPC_SECRET"];
        if($secret != $request->headers->get("X-IPC-Secret")){
            throw new HttpException(403, 'Accès refusé');
        };

        $decodedRequest = json_decode($request->getContent());

        $userId = $decodedRequest->userId;
        $content = $decodedRequest->message;
        $channelId = $decodedRequest->channelId;
        $attachment = $decodedRequest->attachment;

        // On vérifie si l'utilisateur existe et si il a acces au channel susnommé
        // Test initial, complétée avec une vérification des permissions
        $userInfo = $userRep->findWithChannelAccess($userId, $channelId);
        if(!$userInfo) throw $this->createAccessDeniedException();
        // var_dump($userInfo["roles"]);
        if(!$permissionManager->canAccessChannel($channelId, $userId, $userInfo["roles"], PermissionEnum::Write)){
            throw $this->createAccessDeniedException();
        };

        //Pour l'insert, Doctrine a juste besoin d'une référence vers User et Channel, pas de l'entité hydratée :
        $user = $emi->getReference(User::class, $userId);
        $channel = $emi->getReference(Channel::class, $channelId);
        
        $message = new Message();
        $message->setUser($user);
        $message->setContent($content);
        $message->setTimestamp(new DateTime('now'));
        $message->setAttachment($attachment);
        $message->setChannel($channel);

        $emi->persist($message);
        $emi->flush();

        $server = $channel->getServer();
        $roles = $server->getRoles();
        $recipientIds = [];
        foreach ($server->getUsers() as $member) {
            $userId = $member->getId();
            if ($permissionManager->canAccessChannel($channelId, $userId, $roles, PermissionEnum::Read)) {
                $recipientIds[] = $userId;
            }
        }
        return new JsonResponse(["status" => "ok", "recipients" => $recipientIds]);
    }
}
