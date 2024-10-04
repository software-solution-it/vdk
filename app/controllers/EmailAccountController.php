<?php

include_once __DIR__ . '/../services/EmailAccountService.php';
include_once __DIR__ . '/../config/database.php';

class EmailAccountController {
    private $emailAccountService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailAccountService = new EmailAccountService($db);
    }

    public function createEmailAccount() {
        $data = json_decode(file_get_contents("php://input"), true);
        $result = $this->emailAccountService->createEmailAccount($data);
        echo json_encode($result);
    }

    public function updateEmailAccount($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $result = $this->emailAccountService->updateEmailAccount($id, $data);
        echo json_encode($result);
    }

    public function deleteEmailAccount($id) {
        $result = $this->emailAccountService->deleteEmailAccount($id);
        echo json_encode($result);
    }
    
    public function getEmailAccountById($id) {
        $result = $this->emailAccountService->getEmailAccountById($id);
        echo json_encode($result);
    }
}
