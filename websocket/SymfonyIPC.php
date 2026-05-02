<?php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use Symfony\Component\Dotenv\Dotenv;
use function React\Async\await;

class SymfonyIPC
{

    private $dotenv;
    private $secret;
    private $browser;

    public function __construct()
    {
        $this->dotenv = new Dotenv();
        $this->dotenv->load(__DIR__ . '/../.env');

        $this->secret = $_ENV["IPC_SECRET"];

        $this->browser = new Browser();

    }
    
    // Retourne les infos de l'utilisateur si son token est valide, sinon return false
    public function checkCredentials($token){
        // sur docker l'host d'un conteneur c'est son nom
        try {
            $response = await($this->browser->post("http://web:80/websocket/login", ["X-IPC-Secret" => $this->secret, 'Content-Type' => 'application/json'], json_encode(["token" => $token])));
            return json_decode($response->getBody());
        } catch (\React\Http\Message\ResponseException $e) {
            // Log l'erreur sans crasher le serveur
            echo "[SymfonyIPC] Erreur HTTP {$e->getResponse()->getStatusCode()} dans checkCredentials\n";
            return false;
        } catch (\Exception $e) {
            echo "[SymfonyIPC] Erreur inattendue : {$e->getMessage()}\n";
            return false;
        }
    }

    public function saveNewMessage($userId, $content, $attachment, $channelId){
        try {
            $this->browser->post("http://web:80/websocket/saveMessage", ["X-IPC-Secret" => $this->secret, 'Content-Type' => 'application/json'], json_encode(["userId" => $userId, "message" => $content, "channelId" => $channelId, "attachment" => $attachment]));
        } catch (\React\Http\Message\ResponseException $e) {
            echo "[SymfonyIPC] Erreur HTTP {$e->getResponse()->getStatusCode()} dans saveNewMessage\n";
            return false;
        } catch (\Exception $e) {
            echo "[SymfonyIPC] Erreur inattendue : {$e->getMessage()}\n";
            return false;
        }
    }
}



