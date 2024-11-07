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
        http_response_code(200);
        echo json_encode([
            'Status' => 'Success',
            'Message' => 'Users retrieved successfully.',
            'Data' => $users
        ]);
    }

    public function getUserById() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;
        if ($id) { 
            $user = $this->userService->getUserById($id);
            if ($user) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'User retrieved successfully.',
                    'Data' => $user
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'User not found',
                    'Data' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'User ID is required',
                'Data' => null
            ]);
        }
    }

    public function createUser() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->name) && !empty($data->email) && !empty($data->password) && !empty($data->role_id)) {
            $create = $this->userService->createUser($data->name, $data->email, $data->password, $data->role_id);

            http_response_code($create['status'] ? 201 : 500);
            echo json_encode([
                'Status' => $create['status'] ? 'Success' : 'Error',
                'Message' => $create['status'] ? 'User created successfully' : 'Failed to create user',
                'Data' => $create['status'] ? null : $create['message']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Incomplete data. Name, email, password, and role_id are required.',
                'Data' => null
            ]);
        }
    }

    public function updateUser() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id) && !empty($data->name) && !empty($data->email) && !empty($data->role_id)) {
            $user = $this->userService->getUserById($data->id);
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'User not found',
                    'Data' => null
                ]);
                return;
            }

            $update = $this->userService->updateUser($data->id, $data->name, $data->email, $data->role_id);

            http_response_code($update['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $update['status'] ? 'Success' : 'Error',
                'Message' => $update['status'] ? 'User updated successfully' : 'Failed to update user',
                'Data' => null
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Incomplete data. ID, name, email, and role_id are required.',
                'Data' => null
            ]);
        }
    }

    public function deleteUser() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;

        if ($id) {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'User not found',
                    'Data' => null
                ]);
                return;
            }

            $delete = $this->userService->deleteUser($id);

            http_response_code($delete['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $delete['status'] ? 'Success' : 'Error',
                'Message' => $delete['status'] ? 'User deleted successfully' : 'Failed to delete user',
                'Data' => null
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'User ID is required',
                'Data' => null
            ]);
        }
    }

    public function checkUserAccess() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!empty($data['user_id']) && !empty($data['functionality_name'])) {
            $user = $this->userService->getUserById($data['user_id']);

            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'User not found',
                    'Data' => null
                ]);
                return;
            }

            $hasAccess = $this->userService->checkUserAccess($data['user_id'], $data['functionality_name']);

            if ($hasAccess) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Access granted to ' . $data['functionality_name'],
                    'Data' => null
                ]);
            } else {
                http_response_code(403);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Access denied',
                    'Data' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'User ID and functionality name are required',
                'Data' => null
            ]);
        }
    }

    public function verifyCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'));

        if (!empty($data->email) && !empty($data->verification_code)) {
            $result = $this->authService->verifyLoginCode($data->email, $data->verification_code);

            if (isset($result['token'])) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Verification successful.',
                    'Data' => ['token' => $result['token']]
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => $result['message'],
                    'Data' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Email and verification code are required.',
                'Data' => null
            ]);
        }
    }
}
