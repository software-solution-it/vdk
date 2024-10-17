<?php
namespace App\Models;
use PDO;
class EmailThread {
    private $conn;
    private $table = "email_threads";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function createThread($thread_id, $subject, $email_account_id, $date_created) {
        $query = "INSERT INTO " . $this->table . " (thread_id, subject, email_account_id, date_created) 
                  VALUES (:thread_id, :subject, :email_account_id, :date_created)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->bindParam(':date_created', $date_created);

        return $stmt->execute();
    }


    public function getThreadById($thread_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE thread_id = :thread_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function getThreadsByEmailAccountId($email_account_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE email_account_id = :email_account_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
