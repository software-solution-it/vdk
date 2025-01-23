<?php
namespace App\Controllers;

use App\Services\FolderAssociationService;
use App\Config\Database;
use App\Models\FolderAssociation;
use Exception;

class FolderAssociationController {
    private $folderAssociationService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->folderAssociationService = new FolderAssociationService(new FolderAssociation($db));
        $this->errorLogController = new ErrorLogController();
    }

    public function associateFolder() {
        header('Content-Type: application/json');
    
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            $emailAccountId = $data['email_account_id'] ?? null;
            $folderId = $data['folder_id'] ?? null;
            $folderType = $data['folder_type'] ?? null; // INBOX, SPAM, TRASH
    
            // Log inicial
            $this->errorLogController->logError(
                "Requisição de associação de pasta",
                __FILE__,
                __LINE__,
                null,
                json_encode([
                    'request_data' => $data,
                    'email_account_id' => $emailAccountId,
                    'folder_id' => $folderId,
                    'folder_type' => $folderType,
                    'raw_request' => file_get_contents('php://input')
                ], JSON_PRETTY_PRINT)
            );
    
            if (empty($emailAccountId) || empty($folderId) || empty($folderType)) {
                
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Missing required parameters.',
                    'Data' => null
                ]);
                return;
            }
    
    
            $result = $this->folderAssociationService->createOrUpdateAssociation(
                $emailAccountId,
                $folderId,
                $folderType
            );
    
            if (!is_array($result)) {
                throw new Exception('Invalid service response format');
            }
    
            if ($result['success']) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => $result['message'],
                    'Data' => $result['data']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => $result['message'] ?? 'Unknown error',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro na associação de pasta: " . $e->getMessage(),
                __FILE__,
                __LINE__,
                null,
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
    
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Internal server error: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    
    

    public function getAssociationsByEmailAccount($emailAccountId) {
        header('Content-Type: application/json');

        if (empty($emailAccountId)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing email account ID.',
                'Data' => null
            ]);
            return;
        }

        try {
            $associations = $this->folderAssociationService->getAssociationsByEmailAccountList($emailAccountId);

            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Associations retrieved successfully.',
                'Data' => $associations
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error retrieving associations: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }


}
