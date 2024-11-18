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

    public function syncFolders($email_id, $folders) {
        $querySelect = "SELECT id, folder_name FROM " . $this->table . " WHERE email_id = :email_id";
        $stmtSelect = $this->conn->prepare($querySelect);
        $stmtSelect->bindParam(':email_id', $email_id);
        $stmtSelect->execute();
        $existingFolders = $stmtSelect->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna um array associativo [folder_name => id]
    
        $foldersToAdd = array_diff($folders, array_keys($existingFolders));
        $foldersToDelete = array_diff(array_keys($existingFolders), $folders);
    
        $queryInsert = "INSERT INTO " . $this->table . " (email_id, folder_name) VALUES (:email_id, :folder_name)";
        $stmtInsert = $this->conn->prepare($queryInsert);
    
        foreach ($foldersToAdd as $folder_name) {
            $stmtInsert->bindParam(':email_id', $email_id);
            $stmtInsert->bindParam(':folder_name', $folder_name);
            $stmtInsert->execute();
    
            $existingFolders[$folder_name] = $this->conn->lastInsertId();
        }
    
        $queryDelete = "DELETE FROM " . $this->table . " WHERE email_id = :email_id AND folder_name = :folder_name";
        $stmtDelete = $this->conn->prepare($queryDelete);
    
        foreach ($foldersToDelete as $folder_name) {
            $stmtDelete->bindParam(':email_id', $email_id);
            $stmtDelete->bindParam(':folder_name', $folder_name);
            $stmtDelete->execute();
    
            unset($existingFolders[$folder_name]);
        }
    
        return $existingFolders;
    }
    
    public function getFoldersByEmailAccountId($email_id) {
        try {
            $query = "SELECT folder_id FROM " . $this->table . " WHERE email_id = :email_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmt->execute();
    
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao obter pastas por Email Account ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao obter pastas por Email Account ID: ' . $e->getMessage());
        }
    }
    
}
