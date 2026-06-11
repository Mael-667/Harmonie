<?php

use Symfony\Component\Dotenv\Dotenv;


require __DIR__."/WebSocketServer.php";
require __DIR__."/SymfonyIPC.php";
require __DIR__."/WebsocketNotifier.php";

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');
$ipcSecret = $_ENV["IPC_SECRET"];

// Démarrer la loop après avoir fini de tout initialiser sinon php ne prend plus rien en compte
$ws = new WebSocketServer();

$notifier = new WebsocketNotifier($ws, $ipcSecret);

$symfonyIPC = new SymfonyIPC();

$ws->onMessage(function($msg, $user) use (&$ws, &$symfonyIPC){
    // $ws->broadcastMessage($msg);
    $msg = json_decode($msg);

    if(isset($msg->type)){
        switch ($msg->type) {
            case "authentification":
                $userDetails = $symfonyIPC->checkCredentials($msg->token);
                if($userDetails){
                    $user->authenticated = true;
                    // TODO: générer un token de session
                    $user->token = $msg->token;
                    $user->id = $userDetails->userId;
                    $user->avatar = $userDetails->userAvatar;
                    $user->pseudo = $userDetails->pseudo;
                    // associer les infos de l'utilisateur a la connexion

                    $preparedMessage = json_encode(connectedUser($user->id));
                    $ws->broadcastMessageTo($preparedMessage, function($connectedUser) use (&$msg, &$user){
                        return in_array($user->id, $connectedUser->userList);
                    });
                } else {
                    //todo: message d'erreur si le token est erroné
                    // rediriger l'utilisateur vers la page de connexion
                }
                break;
            case "message":
                if(!validateWsMessage($user, $msg)) return;
                
                // on attend le retour de cette fonction pour savoir si l'utilisateur a l'acces ou non, elle retournera les utilisateurs qui pourront voir le msssage
                $ans = $symfonyIPC->saveNewMessage($user->id, $msg->content, $msg->attachment, $msg->channel);
                if($ans){
                    $recipientIds = $ans->recipients;
                    $messageId = $ans->messageId;
                    $preparedMessage = json_encode(newMessage($user, $msg->content, $msg->attachment, $msg->channel, $messageId));
                    $ws->broadcastMessageTo($preparedMessage, function($user) use (&$recipientIds){
                        return in_array($user->id, $recipientIds, true);
                    });
                };
                break;
            case "messageEdit":
                if(!validateWsMessage($user, $msg)) return;

                $messageId = $msg->messageId;
                if(!is_numeric($messageId)) return;

                $ans = $symfonyIPC->editMessage($user->id, $messageId, $msg->content, $msg->attachment, $msg->channel);
                if($ans){
                    $recipientIds = $ans->recipients;
                    $preparedMessage = json_encode(editedMessage($msg->content, $msg->attachment, $ans->channelId, $messageId));
                    $ws->broadcastMessageTo($preparedMessage, function($user) use (&$recipientIds){
                        return in_array($user->id, $recipientIds, true);
                    });
                };
                break;
            case "messageDelete":
                if(!$user->authenticated) return false;
                if(!is_numeric($msg->messageId)) return false;

                $ans = $symfonyIPC->deleteMessage($user->id, $msg->messageId);
                if($ans){
                    $recipientIds = $ans->recipients;
                    $preparedMessage = json_encode(deletedMessage($msg->messageId));
                    $ws->broadcastMessageTo($preparedMessage, function($user) use (&$recipientIds){
                        return in_array($user->id, $recipientIds, true);
                    });
                };
                break;
            case "whichOnline":
                if(!$user->authenticated) return false;

                $serverUsers = $msg->users;
                $user->userList = $serverUsers;
                $connectedUsersIds = [];
                $users = $ws->getAllUsers();
                foreach($users as $u){
                    if(in_array($u->id, $serverUsers)){
                        $connectedUsersIds[] = $u->id;
                    }
                }

                $preparedMessage = json_encode(["type" => "usersStatus", "payload" => ["connectedUsers" => $connectedUsersIds]]);
                $ws->respond($user, $preparedMessage);
                break;
            default:
                //todo: renvoyer un message d'erreur requete invalide
                break;
        }
    } else {
        var_dump($msg);
    }
        
});

$ws->onClose(function($disconnectedUser) use (&$ws){
    $preparedMessage = json_encode(disconnectedUser($disconnectedUser->id));
    $ws->broadcastMessageTo($preparedMessage, function($user) use (&$disconnectedUser){
        return in_array($disconnectedUser->id, $user->userList);
    });
});

function validateWsMessage($user, $msg): bool{
    if(!$user->authenticated) return false;

    $content = trim($msg->content);
    $attachment = trim($msg->attachment);

    if($content == "" && $attachment == "") return false;
    // TODO: renvoyer une véritable erreur si le msg est empty

    $channel = $msg->channel;
    if(!is_numeric($channel)) return false;

    return true;
}


function newMessage($user, $content, $attachment, $channelId, $messageId){
    return [
        "type" => "newMessage",
        "payload" => [
            "attachment" => $attachment,
            "authorAvatar" => $user->avatar,
            "authorId" => $user->id,
            "authorPseudo" => $user->pseudo,
            "content" => $content,
            "timestamp" => new DateTime('now'),
            "channel" => $channelId,
            "id" => $messageId
        ]    
    ];
}

function editedMessage($content, $attachment, $channelId, $messageId){
    return [
        "type" => "editMessage",
        "payload" => [
            "attachment" => $attachment,
            "content" => $content,
            "channel" => $channelId,
            "id" => $messageId
        ]    
    ];
}

function deletedMessage($messageId){
    return [
        "type" => "deleteMessage",
        "payload" => [
            "id" => $messageId
        ]    
    ];
}

function connectedUser($userId){
    return [
        "type" => "connectedUser",
        "payload" => [
            "userId" => $userId
        ]    
    ];
}

function disconnectedUser($userId){
    return [
        "type" => "disconnectedUser",
        "payload" => [
            "userId" => $userId
        ]    
    ];
}


$ws->run();

// $ws->sendMessage();


