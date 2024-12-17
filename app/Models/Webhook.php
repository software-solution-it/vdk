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
        $query = "INSERT INTO webhooks (email_account_id, url, secret, name) 
                  VALUES (:email_account_id, :url, :secret, :name)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':email_account_id', $data['email_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(':url', $data['url'], PDO::PARAM_STR);
        $stmt->bindParam(':secret', $data['secret'], PDO::PARAM_STR);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function getEventsList($email_account_id, $event_id = null, $limit = 10, $order = 'DESC') {
        $query = "SELECT id, webhook_id, event_type, payload, status, created_at 
                  FROM events 
                  WHERE email_account_id = :email_account_id";
    
    
        if (!empty($event_id)) {
            $query .= " AND id = :event_id";
        }
    
        $query .= " ORDER BY created_at $order LIMIT :limit";
    
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(':email_account_id', $email_account_id, PDO::PARAM_INT);
        if (!empty($event_id)) {
            $stmt->bindParam(':event_id', $event_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    

    public function getWebhooksByEmailAccountId($user_id) {
        $query = "SELECT * FROM webhooks WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $query = "UPDATE webhooks 
                  SET url = :url, secret = :secret, name = :name, updated_at = NOW() 
                  WHERE id = :id";
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
        $query = "INSERT INTO events (webhook_id, event_type, payload, email_account_id, status) 
                  VALUES (:webhook_id, :event_type, :payload, :email_account_id, :status)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':webhook_id', $data['webhook_id'], PDO::PARAM_INT);
        $stmt->bindParam(':event_type', $data['event_type'], PDO::PARAM_STR);
        $stmt->bindParam(':payload', $data['payload'], PDO::PARAM_STR);
        $stmt->bindParam(':email_account_id', $data['email_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);

        return $stmt->execute();
    }
}
