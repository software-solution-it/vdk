<?php

namespace App\Controllers;

use App\Services\EmailSyncService;
use App\Config\Database;

class EmailSyncController {
    private $emailSyncService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();

        $this->emailSyncService = new EmailSyncService($db);
    }

    public function startConsumer() {
        $data = json_decode(file_get_contents('php://input'), true);
    
        if (!isset($data['user_id']) || !isset($data['provider_id'])) {
            echo json_encode(['status' => false, 'message' => 'user_id and provider_id are required.']);
            return;
        }
    
        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
    
        if ($user_id <= 0 || $provider_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }
    
        $this->emailSyncService->startConsumer($user_id, $provider_id);
    
        echo json_encode(['status' => true, 'message' => 'RabbitMQ consumer started for user ' . $user_id . ' and provider ' . $provider_id]);
    }
}
