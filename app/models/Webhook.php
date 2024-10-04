<?php

class Webhook {
    private $conn;
    private $table = "webhooks";

    public $id;
    public $user_id;
    public $url;
    public $token;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($data) {
        $query = "INSERT INTO " . $this->table . " SET user_id=:user_id, url=:url, token=:token";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $data['user_id']);
        $stmt->bindParam(":url", $data['url']);
        $stmt->bindParam(":token", $data['token']);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    public function getWebhooks() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
