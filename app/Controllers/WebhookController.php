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

    public function updateWebhook($id) {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));
    
        if (!empty($data->url) && !empty($data->secret) && !empty($data->name)) {
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
                'url' => $data->url,
                'secret' => $data->secret,
                'name' => $data->name
            ];
    
            if ($this->webhookService->updateWebhook($id, $webhookData)) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Webhook updated successfully',
                    'Data' => null
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Unable to update webhook',
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


    public function deleteWebhook($id) {
        header('Content-Type: application/json');
    
        if ($this->webhookService->deleteWebhook($id)) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Webhook deleted successfully',
                'Data' => null
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Unable to delete webhook',
                'Data' => null
            ]);
        }
    }

    public function getList() {
        header('Content-Type: application/json');
    
        $email_account_id = $_GET['email_account_id'] ?? null;
        $event_id = $_GET['event_id'] ?? null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) 
            ? strtoupper($_GET['order']) 
            : 'DESC'; 
    
        try {
            if (empty($email_account_id)) {
                http_response_code(400);
                echo json_encode([ 
                    'Status' => 'Error',
                    'Message' => 'Email account ID is required.',
                    'Data' => null
                ]);
                return;
            }
    
            $events = $this->webhookService->getEventsList($email_account_id, $event_id, $limit, $order);
    
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Events retrieved successfully.',
                'Data' => $events
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Failed to retrieve events: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    
    
}
