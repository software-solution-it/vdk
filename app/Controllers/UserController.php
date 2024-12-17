<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

class UserController {
    private $userService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->userService = new UserService(new \App\Models\User($db));
        $this->errorLogController = new ErrorLogController();
    }

    public function listUsers() {
        header('Content-Type: application/json');
        $response = $this->userService->listUsers();
        http_response_code(200);
        echo json_encode([
            'Status' => 'Success',
            'Message' => 'Users retrieved successfully.',
            'Data' => $response
        ]);
    }

    public function getUserById() {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? null;
        if ($id) { 
            $response = $this->userService->getUserById($id);
            if ($response) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'User retrieved successfully.',
                    'Data' => $response
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
            $response = $this->userService->createUser($data->name, $data->email, $data->password, $data->role_id);
    
            http_response_code($response['status'] ? 201 : 500);
            echo json_encode([
                'Status' => $response['status'] ? 'Success' : 'Error',
                'Message' => $response['message'],
                'Data' => $response['status'] ? $response['data'] : null
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
            $response = $this->userService->updateUser($data->id, $data->name, $data->email, $data->role_id);
            http_response_code($response['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $response['status'] ? 'Success' : 'Error',
                'Message' => $response['status'] ? 'User updated successfully' : 'Failed to update user',
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
    
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'User ID is required',
                'Data' => null
            ]);
            return;
        }
    
        try {
            $response = $this->userService->deleteUser($id);
    
            http_response_code($response['http_code']);
            echo json_encode([
                'Status' => $response['status'] ? 'Success' : 'Error',
                'Message' => $response['message'],
                'Data' => $response['data'] ?? null
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'An error occurred while deleting the user',
                'Data' => null
            ]);
        }
    }
}
