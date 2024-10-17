<?php
namespace App\Models;
use PDO;

class Email {
    private $conn;
    private $table = "emails";

    private $attachmentsTable = "email_attachments";

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
        $references, 
        $in_reply_to, 
        $isRead, 
        $folder,
        $cc,
        $uid
    ) {
        if (is_null($email_id) || is_null($sender)) {
            error_log("Email com dados incompletos: email_id ou sender está nulo. Ignorando...");
            return false; 
        }

        $subject = $subject ?? 'Sem Assunto';
        $body = $body ?? 'Sem Conteúdo';

        $query = "INSERT INTO " . $this->table . " 
                  (user_id, email_id, subject, sender, recipient, body, date_received, `references`, in_reply_to, is_read, folder, cc, uid) 
                  VALUES 
                  (:user_id, :email_id, :subject, :sender, :recipient, :body, :date_received, :references, :in_reply_to, :is_read, :folder, :cc, :uid)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':sender', $sender);
        $stmt->bindParam(':recipient', $recipient);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':date_received', $date_received);
        $stmt->bindParam(':references', $references);
        $stmt->bindParam(':in_reply_to', $in_reply_to);
        $stmt->bindParam(':is_read', $isRead);
        $stmt->bindParam(':folder', $folder);
        $stmt->bindParam(':cc', $cc);
        $stmt->bindParam(':uid', $uid); 
    
        return $stmt->execute();
    }

    public function saveAttachment($email_id, $filename, $mimeType, $size, $content) {
        $query = "INSERT INTO " . $this->attachmentsTable . " 
                  (email_id, filename, mime_type, size, content) 
                  VALUES 
                  (:email_id, :filename, :mime_type, :size, :content)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':mime_type', $mimeType);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':content', $content, PDO::PARAM_LOB);

        return $stmt->execute();
    }

    public function getLastEmailSyncDateByFolder($user_id, $folder) {
        $query = "SELECT MAX(date_received) as last_date 
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id AND folder = :folder";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':folder', $folder);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_date'] ?? null; 
    }


    public function updateLastEmailSyncDateByFolder($user_id, $folder, $lastEmailDate) {
        $query = "UPDATE " . $this->table . " 
                  SET date_received = :last_email_date 
                  WHERE user_id = :user_id 
                  AND folder = :folder 
                  AND date_received = (
                      SELECT max_date FROM (
                          SELECT MAX(date_received) AS max_date FROM " . $this->table . " 
                          WHERE user_id = :user_id AND folder = :folder
                      ) AS temp_table
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':last_email_date', $lastEmailDate);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':folder', $folder);
        
        return $stmt->execute();
    }
    
    public function emailExistsByMessageId($email_id) {
        $query = "SELECT COUNT(*) as count FROM emails WHERE email_id = :email_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
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

    public function getLastEmailSyncDate($user_id) {
        $query = "SELECT MAX(date_received) as last_date 
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
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
