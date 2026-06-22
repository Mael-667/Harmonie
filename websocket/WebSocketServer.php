<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

require __DIR__."/User.php";


class WebSocketServer
{

    private $socket;

    private $callbacks = [];
    private $users = [];

    private $port = 8080;
    private $maxBufferSize;

    public function __construct($maxBufferSize = 4000)
    {
        $this->maxBufferSize = $maxBufferSize;
        $this->socket = new SocketServer('0.0.0.0:'.$this->port);
        // Implémentation du protocole websocket tel que décrit sur cette documentation
        // https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers

        // Gestion des requetes websocket
        $this->socket->on("connection", function (ConnectionInterface $conn) {
            $handshake = false;
            $buffer = '';
            $message = '';

            $user = new User($conn);
            // remplacer par une hashtable
            $this->users[] = $user;

            $conn->on("data", function (string $data) use (&$conn, &$handshake, &$buffer, &$message, &$user) {
                $buffer .= $data;

                if (!$handshake) {
                    if($this->handshake($buffer, $conn, $user)) {
                        $buffer = '';
                        $handshake = true;
                    };
                } else {
                    // On bloque les messages trop volumineux pour éviter les problèmes
                    if(strlen($buffer) > $this->maxBufferSize){
                        $buffer = "";
                        return;
                    }

                    $result = $this->decodeFrame($buffer);
                    if(!$result) return;

                    $message .= $result['data'];

                    if ($result["FIN"] != 0) {
                        // Message entier
                        // On ignore le message de fermeture de connexion
                        if($result["opcode"] == 8) {
                            $message = '';
                            return;
                        }
                        $this->callback("onMessage", $message, $user);
                        $message = '';
                    }
                    $buffer = '';
                }
            });

            $conn->on("close", function() use ($user) {
                $this->callback("onClose", null, $user);
                unset($this->users[array_search($user, $this->users)]);
            });
        });
    }

    public function run(){
        echo "Serveur WebSocket sur ws://localhost:".$this->port.PHP_EOL;

        Loop::run();
    }

    // Gestion du handshake websocket
    private function handshake(&$buffer, &$conn, &$user): bool
    {
        // On attend la fin des headers HTTP (\r\n\r\n)
        if (strpos($buffer, "\r\n\r\n") === false) {
            return false;
        }

        // echo $buffer;
        if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $buffer, $matches)) {
            $conn->end("HTTP/1.1 400 Bad Request\r\n\r\n");
            return false;
        }

        $key = trim($matches[1]);
        $acceptKey = base64_encode(
            sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        if(!$this->callback("onOpen", $buffer, $user)){
            $conn->end("HTTP/1.1 400 Bad Request\r\n\r\n");
            return false;
        };

        $response = implode("\r\n", [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Accept: $acceptKey",
            '',
            '',   // \r\n\r\n final obligatoire
        ]);
        $conn->write($response);
        return true;
    }

    private function decodeFrame(&$buffer)
    {
        // l'ordre de lecture des bits est exactement comme indiqué dans la docu

        //ex: $byte1 = 129 = 10000001
        $byte1 = ord($buffer[0]);
        $FIN = $byte1 >> 7;
        $opcode = ($byte1 & 15);

        //ex: $byte2 = 137 = 10001001
        $byte2 = ord($buffer[1]);
        $mask = $byte2 >> 7;
        $payloadLen = $byte2 & 127;
        // echo $byte2.PHP_EOL;

        if ($payloadLen < 126) {
            $index = 2;
        } else if ($payloadLen == 126) {
            $index = 4;

            // Calcul et récupération de l'extended payload length
            $payloadLen = (ord($buffer[2]) << 8) + ord($buffer[3]);
        } else if ($payloadLen == 127) {
            $index = 10;
            $start = 2;

            // Calcul et récupération de l'extended payload length
            $payloadLen = 0;
            for ($i = 0; $i < 7; $i++) {
                $payloadLen += ord($buffer[$i + $start]);
                $payloadLen <<= 8;
            }
            $payloadLen += ord($buffer[9]);
        }

        
        // frame non complète on return
        $frameLen = $index + 4 + $payloadLen;   // header + masque + payload
        if (strlen($buffer) < $frameLen) return null;   // incomplète → on attend

        $maskingKey = [];
        $maskingKeyLength = 4;
        for ($i = $index; $i < $index + $maskingKeyLength; $i++) {
            $maskingKey[] = $buffer[$i];
        }

        $index += 4;

        // Initialisé ici pour gérer les frames à payload vide (ex: close frame 0x88 0x80,
        // ping/pong sans données) : sinon la boucle ne tourne pas, $decodedPayload reste
        // indéfini et implode() reçoit null → TypeError fatal.
        $decodedPayload = [];
        for ($i = 0; $i < $payloadLen; $i++) {
            $decodedPayload[] = $buffer[$i + $index] ^ $maskingKey[($i) % 4];
        }

        $decodedPayload = implode($decodedPayload);

        // var_dump($decodedPayload);

        return [
            "opcode" => $opcode,
            "FIN" => $FIN,
            "data" => $decodedPayload
        ];
    }

    private function encodeFrame($data)
    {
        $payload = '';
        // $maskingKey = [7, 107, 67, 18];
        // $encodedPayload = '';
        // for ($i = 0; $i < strlen($data); $i++) { 
        //     $encodedPayload.= chr(ord($data[$i])^$maskingKey[($i)%4]);
        // }

        // Set fin then opcode
        $payload[0] = chr((0 | 128) | 1);

        // Set mask
        // $payload[1] = chr(0 | 128);

        // Set payloadLength
        $dataLen = strlen($data);
        if ($dataLen < 126) {
            // $payload[1] = chr(ord($payload[1]) | $dataLen);
            $payload[1] = chr(0 | $dataLen);
        } else if ($dataLen >= 126 && $dataLen < 65535) {
            $payload[1] = chr(0 | 126);
            $payload .= pack('n', $dataLen);   // 16-bit big-endian
        } else {
            $payload[1] = chr(0 | 127);
            $payload .= pack('J', $dataLen);   // 64-bit big-endian
        }

        // On ajoute pas la maskey key a la frame si le masking bit est off

        // for ($i=0; $i < count($maskingKey); $i++) { 
        //     $payload.=chr($maskingKey[$i]);
        // }
        // $payload.=$encodedPayload;
        $payload .= $data;

        return $payload;
    }

    public function callback($event, $data, $user){
        switch ($event) {
            case 'onMessage':
                if(isset($this->callbacks["onMessage"])) ($this->callbacks["onMessage"])($data, $user);
                break;
            case 'onClose':
                if(isset($this->callbacks["onClose"])) ($this->callbacks["onClose"])($user);
                break;
            case 'onOpen':
                if(isset($this->callbacks["onOpen"])) return ($this->callbacks["onOpen"])($data, $user);
                break;
            default:
                # code...
                break;
        }
    }

    public function onMessage(callable $fun){
        $this->callbacks["onMessage"] = $fun;
    }

    public function onClose(callable $fun){
        $this->callbacks["onClose"] = $fun;
    }

    public function onOpen(callable $fun){
        $this->callbacks["onOpen"] = $fun;
    }

    public function broadcastMessage($message){
        foreach ($this->users as $key => $user) {
            if($user->authenticated){
                $user->conn->write($this->encodeFrame($message));
            }
        }
    }

    public function broadcastMessageTo($message, callable $fun){
        foreach ($this->users as $key => $user) {
            if($user->authenticated && $fun($user)){
                $user->conn->write($this->encodeFrame($message));
            }
        }
    }

    public function respond($user, $message){
        $user->conn->write($this->encodeFrame($message));
    }

    public function getAllUsers(){
        return $this->users;
    }
}