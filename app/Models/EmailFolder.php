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

    public function syncFolder($email_id, $folder_name)
    {
        try {
            // Verifica se a pasta já existe
            $query = "SELECT id FROM " . $this->table . " WHERE email_id = :email_id AND folder_name = :folder_name";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id);
            $stmt->bindParam(':folder_name', $folder_name);
            $stmt->execute();
            $existingFolder = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($existingFolder) {
                // Retorna o ID da pasta existente
                return $existingFolder['id'];
            } else {
                // Insere uma nova pasta
                $query = "INSERT INTO " . $this->table . " (email_id, folder_name) VALUES (:email_id, :folder_name)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email_id', $email_id);
                $stmt->bindParam(':folder_name', $folder_name);
                $stmt->execute();
    
                return $this->conn->lastInsertId();
            }
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao sincronizar pasta: ' . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception('Erro ao sincronizar pasta: ' . $e->getMessage());
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
