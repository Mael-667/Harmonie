<?php


require __DIR__."\WebSocketServer.php";
require __DIR__."\SymfonyIPC.php";


// Démarrer la loop après avoir fini de tout initialiser sinon php ne prend plus rien en compte
$ws = new WebSocketServer();

$si = new SymfonyIPC();

$ws->onMessage(function($msg) use (&$ws, &$si){
    // $ws->broadcastMessage($msg);
    $details = json_decode($msg);
    switch ($details->type) {
        case "authentification":
            $userDetails = $si->checkCredentials($details->token);
            if($userDetails){
                // associer les infos de l'utilisateur a la connexion
            } else {
                // message d'erreur si le token est erroné
            }
            break;
        
        default:
            // renvoyer un message d'erreur requete invalide
            break;
    }
});




$ws->run();

// $ws->sendMessage();


