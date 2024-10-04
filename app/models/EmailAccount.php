<?php
include_once __DIR__ . '/../helpers/EncryptionHelper.php';
class EmailAccount {
    private $conn;
    private $table = "email_accounts";
    private $userTable = "users";
    private $providerTable = "providers";

    public function __construct($db) {
        $this->conn = $db;
    }


    public function create($user_id, $email, $provider_id, $password, $oauth_token, $refresh_token) {
        $query = "INSERT INTO " . $this->table . " (user_id, email, provider_id, password, oauth_token, refresh_token) 
                  VALUES (:user_id, :email, :provider_id, :password, :oauth_token, :refresh_token)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':oauth_token', $oauth_token);
        $stmt->bindParam(':refresh_token', $refresh_token);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }


    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEmailAccountByUserIdAndProviderId($user_id, $provider_id) {
        $query = "SELECT ea.id,ea.email, ea.password, p.imap_host, p.imap_port, p.smtp_host, p.smtp_port, p.encryption
                  FROM " . $this->table . " ea
                  INNER JOIN " . $this->userTable . " u ON ea.user_id = u.id
                  INNER JOIN " . $this->providerTable . " p ON ea.provider_id = p.id
                  WHERE ea.user_id = :user_id AND ea.provider_id = :provider_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':provider_id', $provider_id);

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $email, $provider_id, $password, $oauth_token, $refresh_token) {
        $query = "UPDATE " . $this->table . " SET email = :email, provider_id = :provider_id, 
                  password = :password, refresh_token = :refresh_token WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':oauth_token', $oauth_token);
        $stmt->bindParam(':refresh_token', $refresh_token);

        return $stmt->execute();
    }

    public function getByUserId($user_id) {
        $query = "SELECT ea.id, ea.email, p.name AS provider_name, p.smtp_host, p.smtp_port, p.imap_host, 
                         p.imap_port, p.encryption, p.auth_type, ea.oauth_token, ea.refresh_token
                  FROM " . $this->table . " ea
                  INNER JOIN " . $this->providerTable . " p ON ea.provider_id = p.id
                  WHERE ea.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    


    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }
}
