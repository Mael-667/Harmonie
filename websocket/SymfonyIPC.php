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

    private function symfonyIPC($url, $body){
        // sur docker l'host d'un conteneur c'est son nom
        try {
            $response = await($this->browser->post($url, ["X-IPC-Secret" => $this->secret, 'Content-Type' => 'application/json'], $body));
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
    
    // Retourne les infos de l'utilisateur si son token est valide, sinon return false
    public function checkCredentials($token){
        $body = json_encode(["token" => $token]);
        $url = "http://web:80/websocket/login";
        return $this->symfonyIPC($url, $body);
    }

    public function saveNewMessage($userId, $content, $attachment, $channelId){
        $body = json_encode(["userId" => $userId, "message" => $content, "channelId" => $channelId, "attachment" => $attachment]);
        $url = "http://web:80/websocket/saveMessage";
        return $this->symfonyIPC($url, $body);
    }

    public function editMessage($userId, $messageId, $content, $attachment, $channelId){
        $body = json_encode(["userId" => $userId, "messageId" => $messageId, "message" => $content, "channelId" => $channelId, "attachment" => $attachment]);
        $url = "http://web:80/websocket/editMessage";
        return $this->symfonyIPC($url, $body);
    }

    public function deleteMessage($userId, $messageId){
        $body = json_encode(["userId" => $userId, "messageId" => $messageId]);
        $url = "http://web:80/websocket/deleteMessage";
        return $this->symfonyIPC($url, $body);
    }

}



