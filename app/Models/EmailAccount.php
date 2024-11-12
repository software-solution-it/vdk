<?php

namespace App\Models;

use PDO;
use App\Controllers\ErrorLogController; // Importa o controlador de log de erro
use Exception;

class EmailAccount {
    private $conn;
    private $table = "email_accounts";
    private $userTable = "users";
    private $providerTable = "providers";
    private $errorLogController; // Controlador de log de erro

    public function __construct($db) {
        $this->conn = $db;
        $this->errorLogController = new ErrorLogController(); // Inicializa o controlador de log de erro
    }

    public function create($user_id, $email, $provider_id, $password, $oauth_token, $refresh_token, $client_id, $client_secret, $is_basic) {
        $query = "INSERT INTO " . $this->table . " (user_id, email, provider_id, password, oauth_token, refresh_token, client_id, client_secret, is_basic) 
                  VALUES (:user_id, :email, :provider_id, :password, :oauth_token, :refresh_token, :client_id, :client_secret, :is_basic)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':oauth_token', $oauth_token);
        $stmt->bindParam(':refresh_token', $refresh_token);
        $stmt->bindParam(':client_id', $client_id); 
        $stmt->bindParam(':client_secret', $client_secret); 
        $stmt->bindParam(':is_basic', $is_basic); 

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateTokens($id, $oauth_token, $refresh_token) {
        $query = "UPDATE " . $this->table . " SET oauth_token = :oauth_token, refresh_token = :refresh_token WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':oauth_token', $oauth_token);
        $stmt->bindParam(':refresh_token', $refresh_token);
    
        return $stmt->execute();
    }

    public function getById($id) {
        $query = "SELECT ea.*, p.name AS provider_name, p.smtp_host, p.smtp_port, p.imap_host, p.imap_port, p.encryption
                  FROM " . $this->table . " ea
                  INNER JOIN " . $this->providerTable . " p ON ea.provider_id = p.id
                  WHERE ea.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEmailAccountByUserIdAndProviderId($user_id, $email_id) {
        try {

            $query = "SELECT ea.id, ea.email, ea.password, 
                             ea.user_id, ea.provider_id,
                             p.imap_host, p.imap_port, 
                             p.smtp_host, p.smtp_port, 
                             p.encryption,
                             ea.client_id, ea.client_secret, 
                             ea.oauth_token, ea.refresh_token,
                             ea.tenant_id, ea.auth_code,
                             ea.is_basic
                      FROM " . $this->table . " ea
                      INNER JOIN " . $this->userTable . " u ON ea.user_id = u.id
                      INNER JOIN " . $this->providerTable . " p ON ea.provider_id = p.id
                      WHERE ea.user_id = :user_id AND ea.id = :email_id LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':email_id', $email_id);
    
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);


            return $result;

        } catch (Exception $e) {
            $this->errorLogController->logError('Error fetching email account: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Error fetching email account: ' . $e->getMessage());
        }
    }

    public function update($id, $email, $provider_id, $password, $oauth_token, $refresh_token, $client_id, $client_secret, $is_basic = null) {
        $query = "UPDATE " . $this->table . " SET email = :email, provider_id = :provider_id, 
                  password = :password, oauth_token = :oauth_token, refresh_token = :refresh_token, 
                  client_id = :client_id, client_secret = :client_secret";
        
        if ($is_basic !== null) {
            $query .= ", is_basic = :is_basic";
        }
    
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':oauth_token', $oauth_token);
        $stmt->bindParam(':refresh_token', $refresh_token);
        $stmt->bindParam(':client_id', $client_id); 
        $stmt->bindParam(':client_secret', $client_secret); 
    
        if ($is_basic !== null) {
            $stmt->bindParam(':is_basic', $is_basic);
        }
    
        return $stmt->execute();
    }

    public function getEmailAccountByUserId($userId) {
        $query = "SELECT * FROM email_accounts WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmailAccountById($id) {
        $query = "SELECT * FROM email_accounts WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByUserId($user_id) {
        $query = "SELECT 
                        u.name,
                        ea.id, 
                        ea.email, 
                        ea.password,
                        ea.provider_id,
                        p.name AS provider_name, 
                        p.smtp_host, 
                        p.smtp_port, 
                        p.imap_host, 
                        p.imap_port, 
                        p.encryption, 
                        p.auth_type, 
                        ea.oauth_token, 
                        ea.refresh_token,
                        ea.client_id,
                        ea.client_secret 
                  FROM " . $this->table . " ea
                  INNER JOIN users u ON u.id = ea.user_id
                  INNER JOIN " . $this->providerTable . " p ON ea.provider_id = p.id
                  WHERE ea.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists($email_id, $user_id) {
        $query = "SELECT COUNT(*) FROM emails WHERE email_id = :email_id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }
}
