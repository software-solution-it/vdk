<?php

namespace App\Models;

use PDO;

class EmailFolder {
    private $conn;
    private $table = "email_folders";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function saveFolders($email_id, $folders) {
        $query = "INSERT INTO " . $this->table . " (email_id, folder_name) VALUES (:email_id, :folder_name)";
        $stmt = $this->conn->prepare($query);

        foreach ($folders as $folder_name) {
            $stmt->bindParam(':email_id', $email_id);
            $stmt->bindParam(':folder_name', $folder_name);
            $stmt->execute();
        }

        return true;
    }

    public function getFoldersByEmailId($email_id) {
        $query = "SELECT folder_name FROM " . $this->table . " WHERE email_id = :email_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
