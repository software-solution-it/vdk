<?php
namespace App\Models;
use PDO;
class SMTPConfig {
    private $conn;
    private $table = "smtp_config";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function create($user_id, $host, $port, $username, $password, $encryption) {
        $query = "INSERT INTO " . $this->table . " (user_id, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption) 
                  VALUES (:user_id, :host, :port, :username, :password, :encryption)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":host", $host);
        $stmt->bindParam(":port", $port);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":encryption", $encryption);

        return $stmt->execute();
    }


    public function update($id, $host, $port, $username, $password, $encryption) {
        $query = "UPDATE " . $this->table . " 
                  SET smtp_host = :host, smtp_port = :port, smtp_username = :username, smtp_password = :password, smtp_encryption = :encryption 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":host", $host);
        $stmt->bindParam(":port", $port);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":encryption", $encryption);

        return $stmt->execute();
    }

    public function getByUserId($user_id) {
        $query = "SELECT * FROM smtp_config WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    

        return $stmt->fetch(PDO::FETCH_OBJ);
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }
    
}
