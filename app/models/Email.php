<?php
class Email {
    private $conn;
    private $table = "emails";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function saveEmail(
        $user_id, 
        $email_id, 
        $subject, 
        $sender, 
        $recipient, 
        $body, 
        $date_received, 
        $message_id,
        $references, 
        $in_reply_to, 
        $isRead, 
        $isDeleted ,
        $folder
    ) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, email_id, subject, sender, recipient, body, date_received, message_id, `references`, in_reply_to, is_read, folder) 
                  VALUES 
                  (:user_id, :email_id, :subject, :sender, :recipient, :body, :date_received, :message_id, :references, :in_reply_to, :is_read, :folder)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':sender', $sender);
        $stmt->bindParam(':recipient', $recipient);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':date_received', $date_received);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->bindParam(':references', $references);
        $stmt->bindParam(':in_reply_to', $in_reply_to);
        $stmt->bindParam(':is_read', $isRead);  
        $stmt->bindParam(':folder', $folder);  
        
        return $stmt->execute();
    }

    public function emailExistsByMessageId($message_id) {
        $query = "SELECT COUNT(*) as count FROM emails WHERE message_id = :message_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
        return $result['count'] > 0;
    }

    public function getLastEmailDate() {
        $query = "SELECT MAX(date_received) as last_date FROM emails";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['last_date'] ?? null;
    }
    
    public function getEmailsByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
