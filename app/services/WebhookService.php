<?php

class WebhookService {
    private $webhookModel;

    public function __construct($webhookModel) {
        $this->webhookModel = $webhookModel;
    }

    public function registerWebhook($data) {
        return $this->webhookModel->register($data);
    }

    public function triggerWebhook($event) {
        $webhooks = $this->webhookModel->getWebhooks();

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook['url'], $event, $webhook['token']);
        }

        return true;
    }

    private function sendWebhook($url, $event, $token) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['event' => $event]));
        curl_exec($ch);
        curl_close($ch);
    }
}
