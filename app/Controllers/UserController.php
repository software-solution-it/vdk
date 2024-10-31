<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Models\User;
use App\Config\Database;
use App\Services\UserService;
use App\Controllers\ErrorLogController;

class UserController {
    private $userService;
    private $authService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->userService = new UserService(new User($db));
        $this->authService = new AuthService(new User($db));
        $this->errorLogController = new ErrorLogController();
    }

    public function listUsers() {
        header('Content-Type: application/json');
        $users = $this->userService->listUsers();
        echo json_encode($users);
    }

    public function getUserById() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;
        if ($id) {
            $user = $this->userService->getUserById($id);
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'User not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
    }

    public function createUser() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->name) && !empty($data->email) && !empty($data->password) && !empty($data->role_id)) {
            $create = $this->userService->createUser($data->name, $data->email, $data->password, $data->role_id);

            if ($create) {
                http_response_code(201);
                echo json_encode(['message' => 'User created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create user']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data. Name, email, password, and role_id are required.']);
        }
    }

    public function updateUser() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id) && !empty($data->name) && !empty($data->email) && !empty($data->role_id)) {
            $user = $this->userService->getUserById($data->id);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['message' => 'User not found']);
                return;
            }

            $update = $this->userService->updateUser($data->id, $data->name, $data->email, $data->role_id);

            if ($update) {
                echo json_encode(['message' => 'User updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to update user']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data. ID, name, email, and role_id are required.']);
        }
    }

    public function deleteUser() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;

        if ($id) {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['message' => 'User not found']);
                return;
            }

            $delete = $this->userService->deleteUser($id);

            if ($delete) {
                echo json_encode(['message' => 'User deleted successfully']);
            } else {
    
                http_response_code(500);
                echo json_encode(['message' => 'Failed to delete user']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
    }

    public function checkUserAccess() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!empty($data['user_id']) && !empty($data['functionality_name'])) {
            $user = $this->userService->getUserById($data['user_id']);

            if (!$user) {
                http_response_code(404);
                echo json_encode(['message' => 'User not found']);
                return;
            }

            $hasAccess = $this->userService->checkUserAccess($data['user_id'], $data['functionality_name']);

            if ($hasAccess) {
                http_response_code(200);
                echo json_encode(['message' => 'Access granted to ' . $data['functionality_name']]);
            } else {
                http_response_code(403);
                echo json_encode(['message' => 'Access denied']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID and functionality name are required']);
        }
    }

    public function verifyCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'));

        if (!empty($data->email) && !empty($data->verification_code)) {
            $result = $this->authService->verifyLoginCode($data->email, $data->verification_code);

            if (isset($result['token'])) {
                echo json_encode(['token' => $result['token']]);
            } else {
                http_response_code(400);
                echo json_encode(['message' => $result['message']]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email and verification code are required.']);
        }
    }
}
