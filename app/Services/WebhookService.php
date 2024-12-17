<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Webhook;

class WebhookService {
    private $webhookModel;
    private $client;

    public function __construct() {
        $this->webhookModel = new Webhook();
        $this->client = new Client();
    }

    public function triggerEvent($event, $email_account_id) { 
        // Busca webhooks por email_account_id 
        $webhooks = $this->webhookModel->getWebhooksByEmailAccountId($email_account_id); 

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

    public function getEventsList($email_account_id, $event_id = null, $limit = 10, $order = 'DESC') {
        return $this->webhookModel->getEventsList($email_account_id, $event_id, $limit, $order);
    }
    
    
    

    private function sendWebhook($url, $event, $token) {
        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'X-Signature' => $token,
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

    public function updateWebhook($id, $data) {
        try {
            $webhook = $this->webhookModel->getById($id);
            if (!$webhook) {
                return ['status' => false, 'message' => 'Webhook not found'];
            }

            $updated = $this->webhookModel->update($id, $data);
            if ($updated) {
                return [
                    'status' => true,
                    'message' => 'Webhook updated successfully',
                    'data' => $this->webhookModel->getById($id)
                ];
            }

            return ['status' => false, 'message' => 'Failed to update webhook'];
        } catch (\Exception $e) {
            error_log("Erro ao atualizar webhook com ID $id: " . $e->getMessage());
            return ['status' => false, 'message' => 'Internal server error'];
        }
    }

    public function deleteWebhook($id) {
        try {
            $webhook = $this->webhookModel->getById($id);
            if (!$webhook) {
                return ['status' => false, 'message' => 'Webhook not found'];
            }

            $deleted = $this->webhookModel->delete($id);
            if ($deleted) {
                return ['status' => true, 'message' => 'Webhook deleted successfully'];
            }

            return ['status' => false, 'message' => 'Failed to delete webhook'];
        } catch (\Exception $e) {
            error_log("Erro ao deletar webhook com ID $id: " . $e->getMessage());
            return ['status' => false, 'message' => 'Internal server error'];
        }
    }

    public function registerWebhook($data) {
        return $this->webhookModel->register($data);
    }

    public function getWebhooksByEmailAccountId($email_account_id) {
        return $this->webhookModel->getWebhooksByEmailAccountId($email_account_id);
    }
}
