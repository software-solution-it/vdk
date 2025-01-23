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
    
        // Log imediato para teste
        $this->errorLogController->logError(
            "Teste de log",
            __FILE__,
            __LINE__,
            null,
            ['timestamp' => date('Y-m-d H:i:s')]
        );
    
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            $emailAccountId = $data['email_account_id'] ?? null;
            $folderId = $data['folder_id'] ?? null;
            $folderType = $data['folder_type'] ?? null; // INBOX, SPAM, TRASH
    
            // Log inicial
            $this->errorLogController->logError(
                "Iniciando associação de pasta",
                __FILE__,
                __LINE__,
                $emailAccountId,
                [
                    'request_data' => $data,
                    'email_account_id' => $emailAccountId,
                    'folder_id' => $folderId,
                    'folder_type' => $folderType
                ]
            );
    
            if (empty($emailAccountId) || empty($folderId) || empty($folderType)) {
                $this->errorLogController->logError(
                    "Parâmetros inválidos",
                    __FILE__,
                    __LINE__,
                    $emailAccountId,
                    ['data' => $data]
                );
                
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Missing required parameters.',
                    'Data' => null
                ]);
                return;
            }
    
            // Log antes de chamar o service
            $this->errorLogController->logError(
                "Chamando service",
                __FILE__,
                __LINE__,
                $emailAccountId,
                ['params' => [$emailAccountId, $folderId, $folderType]]
            );
    
            $result = $this->folderAssociationService->createOrUpdateAssociation(
                $emailAccountId,
                $folderId,
                $folderType
            );
    
            // Log do resultado
            $this->errorLogController->logError(
                "Resultado do service",
                __FILE__,
                __LINE__,
                $emailAccountId,
                ['result' => $result]
            );
    
            if ($result['Status'] === 'Success') {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => $result['Message'],
                    'Data' => $result['Data']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => $result['Message'] ?? 'Unknown error',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro na associação de pasta: " . $e->getMessage(),
                __FILE__,
                __LINE__,
                $emailAccountId ?? null,
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
