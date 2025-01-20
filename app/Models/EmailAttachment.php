<?php
namespace App\Models;
use PDO;
use Exception;

class EmailAttachment {
    
    private $conn;
    private $table = "email_attachments";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function saveAttachment($email_id, $filename, $mime_type, $size) {
        $query = "INSERT INTO " . $this->table . " (email_id, filename, mime_type, size) 
                  VALUES (:email_id, :filename, :mime_type, :size)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':mime_type', $mime_type);
        $stmt->bindParam(':size', $size);

        return $stmt->execute();
    }


    public function getAttachmentsByEmailId($email_id) {
        try {
            $query = "
                SELECT id, filename, mime_type, size, s3_key, content
                FROM " . $this->table . "
                WHERE email_id = :email_id
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar anexos: " . $e->getMessage());
        }
    }

    public function deleteAttachmentsByEmailId($email_id) {
        $query = "DELETE FROM " . $this->table . " WHERE email_id = :email_id";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
    
        return $stmt->execute();
    }

}
