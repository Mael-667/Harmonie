<?php


require __DIR__."/WebSocketServer.php";
require __DIR__."/SymfonyIPC.php";


// Démarrer la loop après avoir fini de tout initialiser sinon php ne prend plus rien en compte
$ws = new WebSocketServer();

$symfonyIPC = new SymfonyIPC();

$ws->onMessage(function($msg, $user) use (&$ws, &$symfonyIPC){
    // $ws->broadcastMessage($msg);
    $message = json_decode($msg);

    if(isset($message->type)){
        switch ($message->type) {
            case "authentification":
                $userDetails = $symfonyIPC->checkCredentials($message->token);
                if($userDetails){
                    $user->authenticated = true;
                    // TODO: générer un token de session
                    $user->token = $message->token;
                    $user->id = $userDetails->userId;
                    $user->channels = $userDetails->userChannels;
                    $user->avatar = $userDetails->userAvatar;
                    $user->pseudo = $userDetails->pseudo;
                    // associer les infos de l'utilisateur a la connexion
                } else {
                    //todo: message d'erreur si le token est erroné
                    // rediriger l'utilisateur vers la page de connexion
                }
                break;
            case "message":
                if(!$user->authenticated) return;

                $content = $message->content;
                $channel = $message->channel;
                $attachment = $message->attachment;
                // on attend le retour de cette fonction pour savoir si l'utilisateur a l'acces ou non, elle retournera les utilisateurs qui pourront voir le msssage
                $ans = $symfonyIPC->saveNewMessage($user->id, $content, $attachment, $channel);
                if($ans){
                    $recipientIds = $ans->recipients;
                    $ws->broadcastMessageTo(json_encode(newMessage($user, $content, $attachment, $channel)), function($user) use (&$recipientIds){
                        return in_array($user->id, $recipientIds, true);
                    });
                };
                break;
            default:
                //todo: renvoyer un message d'erreur requete invalide
                break;
        }
    } else {
        var_dump($message);
    }
        
});


function newMessage($user, $content, $attachment, $channelId){
    return [
        "attachment" => $attachment,
        "authorAvatar" => $user->avatar,
        "authorId" => $user->id,
        "authorPseudo" => $user->pseudo,
        "content" => $content,
        "timestamp" => new DateTime('now'),
        "channel" => $channelId
    ];
}



$ws->run();

// $ws->sendMessage();


