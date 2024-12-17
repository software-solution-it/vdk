<?php

namespace App\Models;

class Role {
    private $conn;
    private $table_name = "roles";

    public $id;
    public $role_name;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (id, role_name)
                  VALUES (:id, :role_name)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':role_name', $this->role_name);

        return $stmt->execute();
    }


    public function getById($id) {
        $query = "SELECT id, role_name FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC); 
    }

    public function getAll() {
        $query = "SELECT id, role_name FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC); 
    }
}
