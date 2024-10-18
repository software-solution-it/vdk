<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Webhook;

class WebhookService {
    private $webhookModel;
    private $client;

    public function __construct() {
        $this->webhookModel = new Webhook();
    }

    public function triggerEvent($event, $user_id) {
        $webhooks = $this->webhookModel->getWebhooksByUserId($user_id);

        foreach ($webhooks as $webhook) {
            $success = $this->sendWebhook($webhook['url'], $event, $webhook['secret']);
            
            $eventData = [
                'webhook_id' => $webhook['id'],
                'event_type' => $event['type'],
                'payload' => json_encode($event),
                'status' => $success ? 'sent' : 'failed'
            ];
            $this->webhookModel->registerEvent($eventData);
        }
    }

    private function sendWebhook($url, $event, $token) {
        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'json' => $event,
                'verify' => true 
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("Erro ao enviar webhook para $url: " . $e->getMessage());
            return false;
        }
    }

    public function registerWebhook($data) {
        return $this->webhookModel->register($data);
    }

    public function getWebhooksByUserId($user_id) {
        return $this->webhookModel->getWebhooksByUserId($user_id);
    }
}
