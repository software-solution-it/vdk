<?php
namespace App\Models;
use Exception;
use PDO;
class User {
    private $conn;
    private $table = "users";
    private $roleTable = "roles";
    private $roleFunctionalityTable = "role_functionality";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $email, $password, $verificationCode, $expirationDateTime, $role_id) {
        $query = "INSERT INTO users (name, email, password, verification_code, code_expiration, role_id, is_verified) 
                  VALUES (:name, :email, :password, :verification_code, :code_expiration, :role_id, 0)";
        
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":verification_code", $verificationCode);
        $stmt->bindParam(":code_expiration", $expirationDateTime);
        $stmt->bindParam(":role_id", $role_id);
    
        try {
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            } else {
                print_r($stmt->errorInfo());
                return false;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function update($id, $name, $email, $role_id, $password = null) {
        if ($password) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $query = "UPDATE " . $this->table . " SET name = :name, email = :email, role_id = :role_id, password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":password", $hashedPassword);
        } else {
            $query = "UPDATE " . $this->table . " SET name = :name, email = :email, role_id = :role_id WHERE id = :id";
            $stmt = $this->conn->prepare($query);
        }
    
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":role_id", $role_id);
    
        return $stmt->execute();
    }
    

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    public function listUsers() {
        $query = "SELECT u.id, u.name, u.email, r.role_name, u.created_at 
                  FROM " . $this->table . " u 
                  JOIN " . $this->roleTable . " r ON u.role_id = r.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
    
        $query = "SELECT id, name, email, password, verification_code, code_expiration, role_id, is_verified, created_at
                  FROM " . $this->table . " 
                  WHERE email = :email";
    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();
    
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    

    public function getUserById($id) {
        $query = "SELECT u.id, u.name, u.email, r.role_name, u.created_at, 
                         e.id AS email_account_id, e.email AS email_account, e.provider_id 
                  FROM " . $this->table . " u 
                  JOIN " . $this->roleTable . " r ON u.role_id = r.id 
                  LEFT JOIN email_accounts e ON u.id = e.user_id 
                  WHERE u.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;  
        }
        
        $userData = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_name' => $user['role_name'],
            'created_at' => $user['created_at'],
            'email_accounts' => [] 
        ];
        
        if ($user['email_account_id']) {
            $userData['email_accounts'][] = [
                'id' => $user['email_account_id'],
                'email' => $user['email_account'],
                'provider_id' => $user['provider_id']
            ];
        }
        
        return $userData;
    }
    
    
    

    public function updateLoginVerificationCode($user_id, $verificationCode, $expirationTime) {
        $query = "UPDATE users SET verification_code = :verificationCode, code_expiration = :expirationTime WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
    
        $formattedExpirationTime = date('Y-m-d H:i:s', $expirationTime); 
        
        $stmt->bindParam(":verificationCode", $verificationCode);
        $stmt->bindParam(":expirationTime", $formattedExpirationTime);
        $stmt->bindParam(":user_id", $user_id);
    
        return $stmt->execute();
    }
    public function checkUserAccess($user_id, $functionality_name) {
        $query = "SELECT rf.functionality_name 
                  FROM " . $this->table . " u 
                  JOIN " . $this->roleTable . " r ON u.role_id = r.id 
                  JOIN " . $this->roleFunctionalityTable . " rf ON r.id = rf.role_id 
                  WHERE u.id = :user_id AND rf.functionality_name = :functionality_name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":functionality_name", $functionality_name);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }


    public function getAllRoles() {
        $query = "SELECT id, role_name FROM " . $this->roleTable;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function updateRole($id, $role_id) {
        $query = "UPDATE " . $this->table . " SET role_id = :role_id WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":role_id", $role_id);

        return $stmt->execute();
    }


    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
    
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            if (password_verify($password, $user['password'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    

    public function updateForgotPasswordCode($email, $forgotPasswordCode, $expirationDateTime) {
        $query = "UPDATE users 
                  SET forgot_password_code = :forgot_password_code, forgot_password_expiration = :forgot_password_expiration 
                  WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(":forgot_password_code", $forgotPasswordCode);
        $stmt->bindParam(":forgot_password_expiration", $expirationDateTime);
        $stmt->bindParam(":email", $email);
    
        return $stmt->execute();
    }


    public function clearForgotPasswordCode($email) {
        $query = "UPDATE users 
                  SET forgot_password_code = NULL, forgot_password_expiration = NULL 
                  WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
    
        return $stmt->execute();
    }


    public function updatePassword($email, $newPassword) {
        $query = "UPDATE users SET password = :password WHERE email = :email";
        $stmt = $this->conn->prepare($query);
    
        $stmt->bindParam(":password", $newPassword);
        $stmt->bindParam(":email", $email);
    
        return $stmt->execute();
    }
}
