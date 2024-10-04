<?php

include_once '../config/database.php';
include_once '../models/Webhook.php';
include_once '../services/WebhookService.php';

class WebhookController {
    private $webhookService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->webhookService = new WebhookService(new Webhook($db));
    }

    public function registerWebhook() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->url) && !empty($data->token)) {
            $webhookData = [
                'user_id' => $data->user_id,
                'url' => $data->url,
                'token' => $data->token
            ];

            if ($this->webhookService->registerWebhook($webhookData)) {
                http_response_code(201);
                echo json_encode(['message' => 'Webhook registered successfully']);
            } else {
                http_response_code(503);
                echo json_encode(['message' => 'Unable to register webhook']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }

    public function triggerWebhook() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->event)) {
            $event = $data->event;

            if ($this->webhookService->triggerWebhook($event)) {
                http_response_code(200);
                echo json_encode(['message' => 'Webhook triggered successfully']);
            } else {
                http_response_code(503);
                echo json_encode(['message' => 'Unable to trigger webhook']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'No event data found']);
        }
    }
}
