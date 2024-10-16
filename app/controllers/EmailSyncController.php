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
    
        $user_id = $data['user_id'];
        $provider_id = $data['provider_id']; 
    
        $this->emailSyncService->startConsumer($user_id, $provider_id);
    
        $command = "php /app/email_consumer.php {$user_id} {$provider_id} > /dev/null 2>&1 &";
        exec($command);
    
        echo json_encode(['status' => true, 'message' => 'RabbitMQ consumer started for user ' . $user_id . ' and provider ' . $provider_id]);
    }
}
