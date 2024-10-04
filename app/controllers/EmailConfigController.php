<?php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../services/EmailConfigService.php';

class EmailConfigController {
    private $emailConfigService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailConfigService = new EmailConfigService($db);
    }
    public function createEmailConfig() {
        $data = json_decode(file_get_contents('php://input'), true);
        $response = $this->emailConfigService->createEmailConfig($data);
        echo json_encode($response);
    }
    public function updateEmailConfig($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $response = $this->emailConfigService->updateEmailConfig($id, $data);
        echo json_encode($response);
    }

    public function deleteEmailConfig($id) {
        $response = $this->emailConfigService->deleteEmailConfig($id);
        echo json_encode($response);
    }

    public function getAllEmailConfigs() {
        $response = $this->emailConfigService->getAllEmailConfigs();
        echo json_encode($response);
    }
}
