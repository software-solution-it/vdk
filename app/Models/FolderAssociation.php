<?php
namespace App\Models;

use PDO;

class FolderAssociation {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createOrUpdateAssociation($emailAccountId, $folderId, $folderType) {
        $likePattern = strtoupper($folderType) . "_PROCESSED";
        $query = "SELECT id FROM email_folders 
                  WHERE folder_name LIKE :like_pattern AND email_account_id = :email_account_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':like_pattern', $likePattern);
        $stmt->bindParam(':email_account_id', $emailAccountId);
        $stmt->execute();
    
        $associatedFolder = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$associatedFolder) {
            throw new \Exception("Processed folder for type '$folderType' not found.");
        }
    
        $associatedFolderId = $associatedFolder['id'];
    
        $query = "SELECT id FROM FolderAssociations 
                  WHERE email_account_id = :email_account_id AND folder_type = :folder_type";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_account_id', $emailAccountId);
        $stmt->bindParam(':folder_type', $folderType);
        $stmt->execute();
    
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($existing) {
            $updateQuery = "UPDATE FolderAssociations 
                            SET folder_id = :folder_id, associated_folder_id = :associated_folder_id 
                            WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':folder_id', $folderId);
            $updateStmt->bindParam(':associated_folder_id', $associatedFolderId);
            $updateStmt->bindParam(':id', $existing['id']);
            return $updateStmt->execute();
        } else {
            $insertQuery = "INSERT INTO FolderAssociations (email_account_id, folder_id, associated_folder_id, folder_type) 
                            VALUES (:email_account_id, :folder_id, :associated_folder_id, :folder_type)";
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':email_account_id', $emailAccountId);
            $insertStmt->bindParam(':folder_id', $folderId);
            $insertStmt->bindParam(':associated_folder_id', $associatedFolderId);
            $insertStmt->bindParam(':folder_type', $folderType);
            return $insertStmt->execute();
        }
    }
    

    public function getAssociationsByEmailAccount($emailAccountId) {
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
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

}
