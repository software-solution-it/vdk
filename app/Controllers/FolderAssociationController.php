<?php
namespace App\Controllers;

use App\Services\FolderAssociationService;
use App\Config\Database;
use App\Models\FolderAssociation;

class FolderAssociationController {
    private $folderAssociationService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->folderAssociationService = new FolderAssociationService(new FolderAssociation($db));
    }

    public function associateFolder() {
        header('Content-Type: application/json');
    
        $data = json_decode(file_get_contents('php://input'), true);
    
        $emailAccountId = $data['email_account_id'] ?? null;
        $folderId = $data['folder_id'] ?? null;
        $folderType = $data['folder_type'] ?? null; // INBOX, SPAM, TRASH
    
        // Validação dos parâmetros
        if (empty($emailAccountId) || empty($folderId) || empty($folderType)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing required parameters.',
                'Data' => null
            ]);
            return;
        }
    
        try {
            $result = $this->folderAssociationService->createOrUpdateAssociation(
                $emailAccountId,
                $folderId,
                $folderType
            );
    
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Folder association created or updated successfully.',
                'Data' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error creating or updating folder association: ' . $e->getMessage(),
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
            $associations = $this->folderAssociationService->getAssociationsByEmailAccount($emailAccountId);

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