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
    
            http_response_code($result['http_code']);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['message'],
                'Data' => $result['data']
            ]);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error creating email account: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function updateEmailAccount($id) {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);
    
        try {
            $result = $this->emailAccountService->updateEmailAccount($id, $data);
            
            http_response_code($result['http_code']);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['message'],
                'Data' => $result['data']
            ]);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error updating email account: ' . $e->getMessage(),
                'Data' => null
            ]); 
        }
    }

    public function deleteEmailAccount($id) {
        header('Content-Type: application/json');
        try {
            $result = $this->emailAccountService->deleteEmailAccount($id);
    
            http_response_code($result['http_code']);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['message'],
                'Data' => $result['data']
            ]);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error deleting email account: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    

    public function getEmailAccountByUserId($id) {
        header('Content-Type: application/json');
        try {
            $result = $this->emailAccountService->getEmailAccountByUserId($id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Email accounts retrieved successfully.',
                'Data' => $result
            ]);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error retrieving email accounts: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function getEmailAccountById($id) {
        header('Content-Type: application/json');
        try {
            $result = $this->emailAccountService->getEmailAccountById($id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Email accounts retrieved successfully.',
                'Data' => $result
            ]);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error retrieving email accounts: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
}

