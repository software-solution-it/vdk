<?php
namespace App\Controllers;

use App\Models\Webhook;
use App\Services\WebhookService;
use App\Config\Database;

class WebhookController {
    private $webhookService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->webhookService = new WebhookService(new Webhook($db));
    }

    public function registerWebhook() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->url) && !empty($data->secret)) {
            if (strpos($data->url, 'https://') !== 0) {
                http_response_code(400);
                echo json_encode(['message' => 'URL must be HTTPS']);
                return;
            }

            $webhookData = [
                'user_id' => $data->user_id,
                'url' => $data->url,
                'secret' => $data->secret
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
