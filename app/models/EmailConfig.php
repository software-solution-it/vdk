<?php

class EmailConfig {
    private $conn;
    private $table = 'email_configs';

    public function __construct($db) {
        $this->conn = $db;
    }


    public function create($email_account_id, $smtp_host, $smtp_port, $imap_host, $imap_port, $encryption) {
        $query = "INSERT INTO " . $this->table . " (email_account_id, smtp_host, smtp_port, imap_host, imap_port, encryption) 
                  VALUES (:email_account_id, :smtp_host, :smtp_port, :imap_host, :imap_port, :encryption)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->bindParam(':smtp_host', $smtp_host);
        $stmt->bindParam(':smtp_port', $smtp_port);
        $stmt->bindParam(':imap_host', $imap_host);
        $stmt->bindParam(':imap_port', $imap_port);
        $stmt->bindParam(':encryption', $encryption);

        return $stmt->execute();
    }


    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function update($id, $smtp_host, $smtp_port, $imap_host, $imap_port, $encryption) {
        $query = "UPDATE " . $this->table . " 
                  SET smtp_host = :smtp_host, smtp_port = :smtp_port, imap_host = :imap_host, imap_port = :imap_port, encryption = :encryption 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':smtp_host', $smtp_host);
        $stmt->bindParam(':smtp_port', $smtp_port);
        $stmt->bindParam(':imap_host', $imap_host);
        $stmt->bindParam(':imap_port', $imap_port);
        $stmt->bindParam(':encryption', $encryption);

        return $stmt->execute();
    }


    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }


    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
