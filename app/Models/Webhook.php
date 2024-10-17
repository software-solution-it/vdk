<?php
namespace App\Models;

use PDO;

class Webhook {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($data) {
        $query = "INSERT INTO webhooks (user_id, url, secret) VALUES (:user_id, :url, :secret)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':url', $data['url']);
        $stmt->bindParam(':secret', $data['secret']);

        return $stmt->execute();
    }

    public function getWebhooksByUserId($user_id) {
        $query = "SELECT * FROM webhooks WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registerEvent($data) {
        $query = "INSERT INTO events (webhook_id, event_type, payload, status) VALUES (:webhook_id, :event_type, :payload, :status)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':webhook_id', $data['webhook_id']);
        $stmt->bindParam(':event_type', $data['event_type']);
        $stmt->bindParam(':payload', $data['payload']);
        $stmt->bindParam(':status', $data['status']);

        return $stmt->execute();
    }
}
