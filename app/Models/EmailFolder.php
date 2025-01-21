<?php

namespace App\Models;
use Exception;
use App\Controllers\ErrorLogController;

use PDO;

class EmailFolder {
    private $conn;
    private $table = "email_folders";

    private $errorLogController; 

    public function __construct($db) {
        $this->conn = $db;
        $this->errorLogController = new ErrorLogController();
    }

    public function getFolderById($folder_id) {
        try {
            $query = "SELECT id, folder_name FROM " . $this->table . " WHERE id = :folder_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $stmt->execute();

            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($folder) {
                return $folder; 
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao obter pasta pelo ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao obter pasta pelo ID: ' . $e->getMessage());
        }
    }

    public function syncFolders($email_id, $folders)
    {
        try {
            $querySelect = "SELECT folder_name, id FROM " . $this->table . " WHERE email_id = :email_id";
            $stmtSelect = $this->conn->prepare($querySelect);
            $stmtSelect->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmtSelect->execute();
            $existingFolders = $stmtSelect->fetchAll(PDO::FETCH_KEY_PAIR); // ['Trash' => id]
    
            $folderIds = [];
    
            foreach ($folders as $folderName) {
                $existingId = null;
                foreach ($existingFolders as $existingFolderName => $id) {
                    if (strcasecmp($folderName, $existingFolderName) === 0) { // Ignora maiúsculas/minúsculas
                        $existingId = $id;
                        break;
                    }
                }
    
                if ($existingId !== null) {
                    $folderIds[$folderName] = $existingId;
                } else {
                    $queryInsert = "INSERT INTO " . $this->table . " (email_id, folder_name) VALUES (:email_id, :folder_name)";
                    $stmtInsert = $this->conn->prepare($queryInsert);
                    $stmtInsert->bindParam(':email_id', $email_id, PDO::PARAM_INT);
                    $stmtInsert->bindParam(':folder_name', $folderName, PDO::PARAM_STR);
                    $stmtInsert->execute();
    
                    $folderIds[$folderName] = $this->conn->lastInsertId();
                }
            }
    
            return $folderIds;
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao sincronizar pastas: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao sincronizar pastas: ' . $e->getMessage());
        }
    }
    
    public function getFoldersByEmailAccountId($email_id) {
        try {
            $query = "SELECT id FROM " . $this->table . " WHERE email_id = :email_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmt->execute();
    
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao obter pastas por Email Account ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao obter pastas por Email Account ID: ' . $e->getMessage());
        }
    }

    public function getFoldersNameByEmailAccountId($email_id) {
        try {
            $query = "
                SELECT 
                    f.folder_name, 
                    f.id,
                    COUNT(DISTINCT e.id) as email_count,
                    COUNT(DISTINCT a.id) as attachment_count,
                    SUM(CASE WHEN a.s3_key IS NOT NULL THEN 1 ELSE 0 END) as s3_attachment_count
                FROM " . $this->table . " f
                LEFT JOIN emails e ON e.folder_id = f.id
                LEFT JOIN email_attachments a ON a.email_id = e.id
                WHERE f.email_id = :email_id
                GROUP BY f.id, f.folder_name
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmt->execute();
    
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao obter pastas por Email Account ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao obter pastas por Email Account ID: ' . $e->getMessage());
        }
    }

    public function deleteFoldersByEmailAccountId($email_id) {
        try {
            // Deletando todas as pastas associadas ao email_account_id
            $query = "DELETE FROM " . $this->table . " WHERE email_id = :email_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            
            // Executando a query
            if ($stmt->execute()) {
                return ['status' => true, 'message' => 'Folders deleted successfully'];
            } else {
                return ['status' => false, 'message' => 'Failed to delete folders'];
            }
        } catch (Exception $e) {
            // Logando o erro
            $this->errorLogController->logError('Error deleting folders by Email Account ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
            return ['status' => false, 'message' => 'Error deleting folders: ' . $e->getMessage()];
        }
    }
    

    

    public function getByFolderName($folder_name) {
        try {
            $query = "SELECT id, folder_name, email_id FROM " . $this->table . " WHERE folder_name = :folder_name";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folder_name', $folder_name, PDO::PARAM_STR);
            $stmt->execute();
        
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao obter pasta por nome de pasta: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao obter pasta por nome de pasta: ' . $e->getMessage());
        }
    }
    
    
    
}
