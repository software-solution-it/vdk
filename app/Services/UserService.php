<?php
namespace App\Services;

use App\Models\User;
use App\Config\Database;
use PDO;

class UserService {
    private $userModel;
    private $db;

    public function __construct(User $userModel) {
        $this->userModel = $userModel;

        $database = new Database();
        $this->db = $database->getConnection();
    }
    public function createUser($name, $email, $password, $role_id) {
        $verificationCode = rand(100000, 999999);
        $expirationDateTime = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        return $this->userModel->create($name, $email, $hashedPassword, $verificationCode, $expirationDateTime, $role_id);
    }

    public function updateUser($id, $name, $email, $role_id) {
        return $this->userModel->update($id, $name, $email, $role_id);
    }

    public function deleteUser($id) {
        return $this->userModel->delete($id);
    }

    public function getUserById($id) {
        return $this->userModel->getUserById($id);
    }

    public function listUsers() {
        return $this->userModel->listUsers();
    }

    public function checkUserAccess($user_id, $functionality_name): bool {
        $roleQuery = "SELECT role_id FROM users WHERE id = :user_id";
        $roleStmt = $this->db->prepare($roleQuery);
        $roleStmt->bindParam(':user_id', $user_id);
        $roleStmt->execute();
        $roleResult = $roleStmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$roleResult) {
            return false;
        }
    
        $role_id = $roleResult['role_id'];
    
    
        $functionalityQuery = "
        SELECT functionality_name 
        FROM role_functionality 
        WHERE role_id = :role_id 
        AND functionality_type = 'api' 
        AND functionality_name LIKE :functionality_name
    ";

    $stmt = $this->db->prepare($functionalityQuery);
    
    $likeFunctionalityName = $functionality_name . "%";
    
    $stmt->bindParam(':role_id', $role_id);
    $stmt->bindParam(':functionality_name', $likeFunctionalityName);
    $stmt->execute();
    $functionalityResult = $stmt->fetch(PDO::FETCH_ASSOC);

    return $functionalityResult ? true : false;
}

    public function findByEmail($email) {
        return $this->userModel->findByEmail($email);
    }

    public function resetPassword($email, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        return $this->userModel->updatePassword($email, $hashedPassword);
    }
}
