<?php

namespace App\Controllers;

use App\Models\Webhook;
use App\Services\WebhookService;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class WebhookController {
    private $webhookService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->webhookService = new WebhookService(new Webhook($db));
        $this->errorLogController = new ErrorLogController();
    }

    public function registerWebhook() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->url) && !empty($data->secret) && !empty($data->name)) {
            if (strpos($data->url, 'https://') !== 0) {
                http_response_code(400);
                echo json_encode(['message' => 'URL must be HTTPS']);
                return;
            }

            $webhookData = [
                'user_id' => $data->user_id,
                'url' => $data->url,
                'secret' => $data->secret,
                'name' => $data->name
            ];

            if ($this->webhookService->registerWebhook($webhookData)) {
                http_response_code(201);
                echo json_encode(['message' => 'Webhook registered successfully']);
            } else {
                $this->errorLogController->logError('Unable to register webhook.', __FILE__, __LINE__);
                http_response_code(503);
                echo json_encode(['message' => 'Unable to register webhook']);
            }
        } else {
            $this->errorLogController->logError('Incomplete data for webhook registration.', __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }

    public function getList() {
        $user_id = $_GET['user_id'] ?? null;

        if (!empty($user_id)) {
            $webhooks = $this->webhookService->getWebhooksByUserId($user_id);
            http_response_code(200);
            echo json_encode($webhooks);
        } else {
            $this->errorLogController->logError('User ID is required for fetching webhooks.', __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
    }
}
