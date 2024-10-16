<?php

namespace App\Models;

class JobQueue {
    private $conn;
    private $table_name = "job_queue";

    public $id;
    public $queue_name;
    public $created_at;
    public $executed_at;
    public $is_executed;
    public $user_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (queue_name, user_id, is_executed, created_at)
                  VALUES (:queue_name, :user_id, 0, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':queue_name', $this->queue_name);
        $stmt->bindParam(':user_id', $this->user_id);
        return $stmt->execute();
    }

    public function getPendingJobsByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id AND is_executed = 0 ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markAsExecuted($id) {
        $query = "UPDATE " . $this->table_name . " SET is_executed = 1, executed_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getJobByQueueName($queue_name) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE queue_name = :queue_name LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':queue_name', $queue_name);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
