<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Service\PermissionManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;


// TODO: sécuriser ces routes avec un token csrf
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
        return new JsonResponse([
            "status" => "ok",
            "recipients" => $recipientIds,
            "messageId" => $message->getId()
        ]);
    }


    #[Route('/websocket/editMessage', name: 'websocket_editMessage')]
    public function editMessage(Request $request, PermissionManager $permissionManager, MessageRepository $msgRep, EntityManagerInterface $emi){
        $secret = $_ENV["IPC_SECRET"];
        if($secret != $request->headers->get("X-IPC-Secret")){
            throw new HttpException(403, 'Accès refusé');
        };

        $decodedRequest = json_decode($request->getContent());

        $userId = $decodedRequest->userId;
        $messageId = $decodedRequest->messageId;
        $content = $decodedRequest->message;
        $attachment = $decodedRequest->attachment;
        
        // On vérifie si l'utilisateur existe et si c'est bien son message
        $ans = $msgRep->findWithMessage($userId, $messageId);
        if(!$ans) throw $this->createAccessDeniedException();
        // var_dump($userInfo["roles"]);
        $roles = $ans["roles"];
        $msg = $ans[0];
        $channelId = $ans["channelId"];
        if(!$permissionManager->canAccessChannel($channelId, $userId, $roles, PermissionEnum::Write)){
            throw $this->createAccessDeniedException();
        };

        //Pour l'insert, Doctrine a juste besoin d'une référence vers User et Channel, pas de l'entité hydratée :
        $channel = $emi->getReference(Channel::class, $channelId);

        $msg->setContent($content);
        // flush suffit pour l'edit
        $emi->flush();

        $server = $channel->getServer();
        $recipientIds = [];
        foreach ($server->getUsers() as $member) {
            $userId = $member->getId();
            if ($permissionManager->canAccessChannel($channelId, $userId, $roles, PermissionEnum::Read)) {
                $recipientIds[] = $userId;
            }
        }
        // on ne trust pas le channel de l'utilisateur car 
        // Conséquence : si un client envoie un channel falsifié, le serveur fait l'update correctement (autorisation OK) mais le payload editMessage broadcasté contient le faux channelId. Côté récepteurs, le test if(message.channel == currentChannelId) (app.js ligne 43) échoue → l'UI ne se met pas à jour pour les
        // destinataires légitimes. Pas une faille de sécurité, mais un bug d'affichage exploitable.
        return new JsonResponse([
            "status" => "ok",
            "recipients" => $recipientIds,
            "messageId" => $messageId,
            "channelId" => $channelId
        ]);
    }

    #[Route('/websocket/deleteMessage', name: 'websocket_deleteMessage')]
    public function deleteMessage(Request $request, PermissionManager $permissionManager, MessageRepository $msgRep, ServerRepository $servRep, EntityManagerInterface $emi,
    #[Autowire('%kernel.project_dir%/public/uploads/attachments')] string $attachmentDir ){
        $secret = $_ENV["IPC_SECRET"];
        if($secret != $request->headers->get("X-IPC-Secret")){
            throw new HttpException(403, 'Accès refusé');
        };

        $decodedRequest = json_decode($request->getContent());

        $userId = $decodedRequest->userId;
        $messageId = $decodedRequest->messageId;

        $msgInfo = $msgRep->getMessageInfo($messageId);

        if ($msgInfo === null) {
            return new JsonResponse(["status" => "not_found"], 404);
        }

        $roles = $msgInfo["roles"];
        $msgUserId = $msgInfo["userId"];
        $msg = $msgInfo[0];
        $hasRight = $permissionManager->hasServerRight($userId, $roles, PermissionEnum::Delete);

        // On vérifie si l'utilisateur a les droits de suppression ou si c'est son message
        if(!$hasRight && $userId != $msgUserId) throw $this->createAccessDeniedException();

        $attachment = $msg->getAttachment();

        $emi->remove($msg);
        $emi->flush();

        if($attachment){
            $attachmentUrl = $attachmentDir."/".$attachment;
            if(is_file($attachmentUrl)){
                if (!unlink($attachmentUrl)) {
                    // TODO: handle échec de la suppression (droits, fichier verrouillé…)
                }
            }
        }

        $recipientIds = [];
        $serverUsers = $servRep->findOneBy(["id" => $msgInfo["serverId"]])->getUsers();
        foreach ($serverUsers as $member) {
            $recipientIds[] = $member->getId();
        }
        
        return new JsonResponse([
            "status" => "ok",
            "recipients" => $recipientIds,
            "messageId" => $messageId,
        ]);
    }
}
