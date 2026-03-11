<?php
require __DIR__ . '/../../vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

$socket = new SocketServer('0.0.0.0:8080');

// Gestion des requetes websocket
$socket->on("connection", function (ConnectionInterface $conn){
    $handshake = false;
    $buffer = '';

    $conn->on("data", function (string $data) use ($conn, &$handshake, $buffer){
        $buffer .= $data;
    
        if(!$handshake){
            handshake($buffer, $conn);
            $buffer = '';
            $handshake = true;
        } else {
            // l'ordre de lecture des bits est exactement comme indiqué dans la docu
            $byte1 = ord($buffer[0]);
            $FIN = $byte1 >> 7;
            $opcode = ($byte1 & 15);

            $byte2 = ord($buffer[1]);
            $mask = $byte2 >> 7;
            $payloadLen = $byte2 & 127;

            $start = 2;
            $maskingKeyLength = 4; 
            for ($i=$start; $i < $start+$maskingKeyLength; $i++) { 
                $maskingKey[] = $buffer[$i];
            }

            $index = 6;
            for ($i=$index; $i < strlen($buffer); $i++) { 
                $decodedPayload[] = $buffer[$i] ^ $maskingKey[($i-$index)%4];
            }

            $decodedPayload = implode($decodedPayload);

            var_dump($decodedPayload);

            // echo ord($buffer[1]);
        }
    });
});


// Gestion du handshake websocket
function handshake(&$buffer, &$conn){
// On attend la fin des headers HTTP (\r\n\r\n)
    if (strpos($buffer, "\r\n\r\n") === false) {
        return;
    }

    // echo $buffer;
        if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $buffer, $matches)) {
        $conn->end("HTTP/1.1 400 Bad Request\r\n\r\n");
        return;
    }

    $key       = trim($matches[1]);
    $acceptKey = base64_encode(
        sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
    );
    
    $response = implode("\r\n", [
        'HTTP/1.1 101 Switching Protocols',
        'Upgrade: websocket',
        'Connection: Upgrade',
        "Sec-WebSocket-Accept: $acceptKey",
        '', '',   // \r\n\r\n final obligatoire
    ]);
    $conn->write($response);
    return;
}

function decode(){

}

echo "Serveur WebSocket sur ws://localhost:8080\n";

Loop::run();