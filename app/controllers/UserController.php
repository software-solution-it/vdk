<?php

include_once __DIR__ . '/../services/UserService.php';
include_once __DIR__ . '/../services/AuthService.php';  // Certifique-se de incluir o AuthService
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';
include_once __DIR__ . '/../helpers/JWTHandler.php';

class UserController {
    private $userService;
    private $authService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->userService = new UserService(new User($db));
        $this->authService = new AuthService(new User($db), new JWTHandler()); // Adicionando o AuthService
    }

    public function listUsers() {
        $users = $this->userService->listUsers();
        echo json_encode($users);
    }

    public function getUserById() {
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
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id) && !empty($data->name) && !empty($data->email) && !empty($data->role_id)) {
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
        $id = $_GET['id'] ?? null;
        if ($id) {
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
        $user_id = $_GET['user_id'] ?? null;
        $functionality_name = $_GET['functionality_name'] ?? null;

        if ($user_id && $functionality_name) {
            $hasAccess = $this->userService->checkUserAccess($user_id, $functionality_name);

            if ($hasAccess) {
                echo json_encode(['message' => 'Access granted']);
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

        $data = json_decode(file_get_contents("php://input"));

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
