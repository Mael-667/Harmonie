<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Http\Message\Response as HttpResponse;
use React\Socket\SocketServer;

class WebsocketNotifier
{
    private $http;

    public function __construct($ws, $ipcSecret)
    {
        $this->http = new HttpServer(function (ServerRequestInterface $req) use (&$ws, $ipcSecret) {
            if ($req->getMethod() !== 'POST')
                return new HttpResponse(405);
            if ($req->getHeaderLine('X-IPC-Secret') !== $ipcSecret)
                return new HttpResponse(403);

            $body = json_decode((string) $req->getBody());
            if (!$body || !isset($body->type))
                return new HttpResponse(400);

            $type = $body->type;

            if (!isset($body->recipientsIds) || !is_array($body->recipientsIds))
                return new HttpResponse(400);
            $recipientIds = $body->recipientsIds;

            $ws->broadcastMessageTo(json_encode(["type" => $type]), function ($user) use (&$recipientIds) {
                return in_array($user->id, $recipientIds, true);
            });
            return HttpResponse::json(["status" => "ok"]);
        });

        $this->http->listen(new SocketServer('0.0.0.0:8081'));
    }

    // message type
    // [
    //      "type" => "reloadChannel",
    //      "recipientsIds" => [1, 2, 3]
    // ]
}