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

    public function saveAttachment($email_id, $filename, $mime_type, $size, $s3_key, $content_hash = null) {
        $query = "INSERT INTO " . $this->table . " 
                  (email_id, filename, mime_type, size, s3_key, content_hash) 
                  VALUES 
                  (:email_id, :filename, :mime_type, :size, :s3_key, :content_hash)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':mime_type', $mime_type);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':s3_key', $s3_key);
        $stmt->bindParam(':content_hash', $content_hash);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getAttachmentsByEmailId($email_id) {
        try {
            $query = "
                SELECT 
                    id, 
                    filename, 
                    mime_type, 
                    size, 
                    s3_key,
                    content_hash
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

    public function getAttachmentByHash($content_hash) {
        try {
            $query = "
                SELECT id, filename, mime_type, size, s3_key, content_hash
                FROM " . $this->table . "
                WHERE content_hash = :content_hash
                LIMIT 1
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':content_hash', $content_hash, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar anexo por hash: " . $e->getMessage());
        }
    }

    public function deleteAttachmentsByEmailId($email_id) {
        $query = "DELETE FROM " . $this->table . " WHERE email_id = :email_id";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
    
        return $stmt->execute();
    }
}
