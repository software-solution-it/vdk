<?php

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

    // Método para criar um novo job
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (queue_name, user_id) 
                  VALUES 
                  (:queue_name, :user_id)";

        $stmt = $this->conn->prepare($query);

        // Vincula os parâmetros
        $stmt->bindParam(':queue_name', $this->queue_name);
        $stmt->bindParam(':user_id', $this->user_id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Método para marcar um job como executado
    public function markAsExecuted($id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_executed = TRUE, executed_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Método para obter todos os jobs pendentes
    public function getPendingJobs() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_executed = FALSE";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
