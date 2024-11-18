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

    public function syncFolders($email_id, $folders)
    {
        try {
            // Obtém as pastas já existentes no banco de dados
            $querySelect = "SELECT id, folder_name FROM " . $this->table . " WHERE email_id = :email_id";
            $stmtSelect = $this->conn->prepare($querySelect);
            $stmtSelect->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmtSelect->execute();
            $existingFolders = $stmtSelect->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna um array associativo [folder_name => id]
    
            $folderIds = [];
    
            // Insere as pastas que não existem
            $queryInsert = "INSERT INTO " . $this->table . " (email_id, folder_name) VALUES (:email_id, :folder_name)";
            $stmtInsert = $this->conn->prepare($queryInsert);
    
            foreach ($folders as $folderName) {
                if (isset($existingFolders[$folderName])) {
                    // Pasta já existe, adiciona o ID ao resultado
                    $folderIds[$folderName] = $existingFolders[$folderName];
                } else {
                    // Insere a nova pasta
                    $stmtInsert->bindValue(':email_id', $email_id, PDO::PARAM_INT);
                    $stmtInsert->bindValue(':folder_name', $folderName, PDO::PARAM_STR);
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
    
    
}
