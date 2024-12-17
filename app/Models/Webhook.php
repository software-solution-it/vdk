<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Webhook {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function register($data) {
        $query = "INSERT INTO webhooks (user_id, url, secret, name) VALUES (:user_id, :url, :secret, :name)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':url', $data['url']);
        $stmt->bindParam(':secret', $data['secret']);
        $stmt->bindParam(':name', $data['name']);

        return $stmt->execute();
    }

    public function getWebhooksByUserId($user_id) {
        $query = "SELECT * FROM webhooks WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $query = "UPDATE webhooks SET url = :url, secret = :secret, name = :name, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':url', $data['url'], PDO::PARAM_STR);
        $stmt->bindParam(':secret', $data['secret'], PDO::PARAM_STR);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM webhooks WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getById($id) {
        $query = "SELECT * FROM webhooks WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
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
