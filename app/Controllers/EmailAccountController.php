<?php
namespace App\Controllers;

use App\Services\EmailAccountService;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class EmailAccountController {
    private $emailAccountService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailAccountService = new EmailAccountService($db);
        $this->errorLogController = new ErrorLogController(); 
    }

    public function createEmailAccount() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $result = $this->emailAccountService->createEmailAccount($data);
            echo json_encode($result);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao criar conta de email: ' . $e->getMessage()]);
        }
    }

    public function updateEmailAccount($id) {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $result = $this->emailAccountService->updateEmailAccount($id, $data);
            echo json_encode($result);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao atualizar conta de email: ' . $e->getMessage()]);
        }
    }

    public function deleteEmailAccount($id) {
        header('Content-Type: application/json');
        try {
            $result = $this->emailAccountService->deleteEmailAccount($id);
            echo json_encode($result);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao excluir conta de email: ' . $e->getMessage()]);
        }
    }

    public function getEmailAccountByUserId($id) {
        header('Content-Type: application/json');
        try {
            $result = $this->emailAccountService->getEmailAccountByUserId($id);
            echo json_encode($result);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao obter contas de email: ' . $e->getMessage()]);
        }
    }
}
