<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebsocketNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(IPC_SECRET)%')] private string $ipcSecret,
        #[Autowire('%env(WEBSOCKET_IPC_URL)%')] private string $url,
    ) {
    }

    public function broadcast(string $payload, ?array $recipients = null): void
    {
        try {
            $this->httpClient->request('POST', $this->url, [
                'headers' => ['X-IPC-Secret' => $this->ipcSecret],
                'json' => ['recipientsIds' => $recipients, 'type' => $payload],
                'timeout' => 1,
            ])->getStatusCode(); // déclenche l'envoi
        } catch (\Throwable $e) {
            // log sans casser la requête HTTP principale (le WS peut être down)
        }
    }
}