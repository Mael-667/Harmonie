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
                $symfonyIPC->saveNewMessage($user->id, $content, $attachment, $channel);

                break;
            default:
                //todo: renvoyer un message d'erreur requete invalide
                break;
        }
    } else {
        var_dump($message);
    }
        
});




$ws->run();

// $ws->sendMessage();


