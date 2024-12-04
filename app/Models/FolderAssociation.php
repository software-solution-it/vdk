<?php
namespace App\Models;

use PDO;
use App\Controllers\ErrorLogController;

class FolderAssociation {
    private $conn;
    private $errorLogController;

    public function __construct($db) {
        $this->conn = $db;
        $this->errorLogController = new ErrorLogController(); // Instanciando o controlador de logs
    }

    public function createOrUpdateAssociation($emailAccountId, $folderId, $folderType) {
        try {
            $likePattern = strtoupper($folderType) . "_PROCESSED";

            // Busca pasta processada associada ao tipo
            $query = "SELECT id FROM email_folders 
                      WHERE folder_name LIKE :like_pattern AND email_account_id = :email_account_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':like_pattern', $likePattern);
            $stmt->bindParam(':email_account_id', $emailAccountId);
            $stmt->execute();

            $associatedFolder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$associatedFolder) {
                // Loga o erro em vez de lançar uma exceção
                $this->errorLogController->logError(
                    "Processed folder for type '$folderType' not found.",
                    __FILE__,
                    __LINE__,
                    null, // Se disponível, passe o ID do usuário ou outro contexto relevante
                    [
                        'emailAccountId' => $emailAccountId,
                        'folderType' => $folderType
                    ]
                );
                return false; // Retorna false se a pasta processada não for encontrada
            }

            $associatedFolderId = $associatedFolder['id'];

            // Verifica se já existe associação para o tipo de pasta
            $query = "SELECT id FROM FolderAssociations 
                      WHERE email_account_id = :email_account_id AND folder_type = :folder_type";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_account_id', $emailAccountId);
            $stmt->bindParam(':folder_type', $folderType);
            $stmt->execute();

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Atualiza associação existente
                $updateQuery = "UPDATE FolderAssociations 
                                SET folder_id = :folder_id, associated_folder_id = :associated_folder_id 
                                WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':folder_id', $folderId);
                $updateStmt->bindParam(':associated_folder_id', $associatedFolderId);
                $updateStmt->bindParam(':id', $existing['id']);
                $updateStmt->execute();

                return true;
            } else {
                // Cria nova associação
                $insertQuery = "INSERT INTO FolderAssociations (email_account_id, folder_id, associated_folder_id, folder_type) 
                                VALUES (:email_account_id, :folder_id, :associated_folder_id, :folder_type)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $insertStmt->bindParam(':email_account_id', $emailAccountId);
                $insertStmt->bindParam(':folder_id', $folderId);
                $insertStmt->bindParam(':associated_folder_id', $associatedFolderId);
                $insertStmt->bindParam(':folder_type', $folderType);
                $insertStmt->execute();

                return true;
            }
        } catch (\Exception $e) {
            // Loga qualquer exceção que ocorra
            $this->errorLogController->logError(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                null, // Se disponível, passe o ID do usuário ou outro contexto relevante
                [
                    'emailAccountId' => $emailAccountId,
                    'folderId' => $folderId,
                    'folderType' => $folderType,
                ]
            );

            return false; // Retorna false em caso de erro
        }
    }

    public function getAssociationsByEmailAccount($emailAccountId) {
        try {
            $query = "
                SELECT 
                    fa.id,
                    fa.email_account_id,
                    fa.folder_id,
                    fa.associated_folder_id,
                    fa.folder_type,
                    ef1.folder_name AS folder_name,
                    ef2.folder_name AS associated_folder_name
                FROM 
                    FolderAssociations fa
                INNER JOIN 
                    email_folders ef1 ON fa.folder_id = ef1.id
                INNER JOIN 
                    email_folders ef2 ON fa.associated_folder_id = ef2.id
                WHERE 
                    fa.email_account_id = :email_account_id
            ";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_account_id', $emailAccountId);
            $stmt->execute();
    
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            if (!empty($data)) {
                return [
                    'Status' => 'Success',
                    'Message' => 'Associations retrieved successfully.',
                    'Data' => $data
                ];
            } else {
                return [
                    'Status' => 'Failure',
                    'Message' => 'No associations found for the given email account ID.',
                    'Data' => []
                ];
            }
        } catch (\Exception $e) {
            // Loga qualquer exceção que ocorra durante a execução
            $this->errorLogController->logError(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                null, // Se disponível, passe o ID do usuário ou outro contexto relevante
                [
                    'emailAccountId' => $emailAccountId
                ]
            );
    
            return [
                'Status' => 'Error',
                'Message' => 'An error occurred while fetching associations.',
                'Data' => []
            ];
        }
    }

    public function getAssociationsByEmailAccountList($emailAccountId) {
        try {
            $query = "
                SELECT 
                    fa.id,
                    fa.email_account_id,
                    fa.folder_id,
                    fa.associated_folder_id,
                    fa.folder_type,
                    ef1.folder_name AS folder_name,
                    ef2.folder_name AS associated_folder_name
                FROM 
                    FolderAssociations fa
                INNER JOIN 
                    email_folders ef1 ON fa.folder_id = ef1.id
                INNER JOIN 
                    email_folders ef2 ON fa.associated_folder_id = ef2.id
                WHERE 
                    fa.email_account_id = :email_account_id
            ";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_account_id', $emailAccountId);
            $stmt->execute();
    
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            return $data;
        } catch (\Exception $e) {
            $this->errorLogController->logError(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                null, 
                [
                    'emailAccountId' => $emailAccountId
                ]
            );
    
            return []; 
        }
    }
    
    
    
}
