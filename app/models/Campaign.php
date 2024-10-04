<?php

class Campaign {
    private $conn;
    private $table = 'campaigns';

    public function __construct($db) {
        $this->conn = $db;
    }


    public function create($email_account_id, $name, $priority) {
        $query = "INSERT INTO " . $this->table . " (email_account_id, name, priority) 
                  VALUES (:email_account_id, :name, :priority)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':priority', $priority);

        return $stmt->execute();
    }

    public function readAll($user_id, $email_account_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id AND email_account_id = :email_account_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function update($id, $name, $priority) {
        $query = "UPDATE " . $this->table . " SET name = :name, priority = :priority WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':priority', $priority);

        return $stmt->execute();
    }


    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }
}
