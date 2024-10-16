<?php
namespace App\Models;
use PDO;
class ScheduledEmails {
    private $conn;
    private $table = 'scheduled_emails';

    public function __construct($db) {
        $this->conn = $db;
    }


    public function create($campaign_id, $name, $recipient_email, $subject, $html_template, $scheduled_at) {
        $query = "INSERT INTO " . $this->table . " (campaign_id, name, recipient_email, subject, html_template, scheduled_at) 
                  VALUES (:campaign_id, :name, :recipient_email, :subject, :html_template, :scheduled_at)";
        
        $stmt = $this->conn->prepare($query);


        $stmt->bindParam(':campaign_id', $campaign_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':recipient_email', $recipient_email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':html_template', $html_template);
        $stmt->bindParam(':scheduled_at', $scheduled_at);

        return $stmt->execute();
    }


    public function readAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
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


    public function update($id, $name, $recipient_email, $subject, $html_template, $scheduled_at) {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, recipient_email = :recipient_email, subject = :subject, html_template = :html_template, scheduled_at = :scheduled_at 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':recipient_email', $recipient_email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':html_template', $html_template);
        $stmt->bindParam(':scheduled_at', $scheduled_at);

        return $stmt->execute();
    }


    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
