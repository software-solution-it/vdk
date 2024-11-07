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
        $this->webhookService = new WebhookService();
        $this->errorLogController = new ErrorLogController();
    }

    public function registerWebhook() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->url) && !empty($data->secret) && !empty($data->name)) {
            if (strpos($data->url, 'https://') !== 0) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'URL must be HTTPS',
                    'Data' => null
                ]);
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
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Webhook registered successfully',
                    'Data' => null
                ]);
            } else {
                http_response_code(503);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Unable to register webhook',
                    'Data' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Incomplete data',
                'Data' => null
            ]);
        }
    }

    public function getList() {
        header('Content-Type: application/json');
        $user_id = $_GET['user_id'] ?? null;

        if (!empty($user_id)) {
            $webhooks = $this->webhookService->getWebhooksByUserId($user_id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Webhooks retrieved successfully.',
                'Data' => $webhooks
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'User ID is required',
                'Data' => null
            ]);
        }
    }
}
