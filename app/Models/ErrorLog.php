<?php
namespace App\Models;

use PDO;

class ErrorLog {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function logError($errorMessage, $file, $line, $userId = null, $additionalInfo = null) {
        $query = "INSERT INTO error_logs (error_message, file, line, user_id, additional_info) 
                  VALUES (:error_message, :file, :line, :user_id, :additional_info)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':error_message', $errorMessage);
        $stmt->bindParam(':file', $file);
        $stmt->bindParam(':line', $line);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':additional_info', $additionalInfo);

        return $stmt->execute();
    }

    public function getLogsByUserId($userId) {
        $query = "SELECT * FROM error_logs WHERE user_id = :user_id ORDER BY timestamp DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function getAllLogs() {
        $query = "SELECT * FROM error_logs ORDER BY timestamp DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
