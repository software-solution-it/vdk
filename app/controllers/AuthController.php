<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\JWTHandler;
use App\Config\Database;

class AuthController {
    private $authService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->authService = new AuthService(new JWTHandler());
    }

    public function login() {
        header('Content-Type: application/json');
    
        $data = json_decode(file_get_contents("php://input"));
    
        $missingFields = [];
        if (empty($data->email)) {
            $missingFields[] = 'email';
        }
        if (empty($data->password)) {
            $missingFields[] = 'password';
        }
    
        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
            return;
        }
    
        $login = $this->authService->login($data->email, $data->password);
    
        if ($login['success']) {
    
            if ($login['role_id'] == 1) {
                http_response_code(200);
                echo json_encode([
                    'message' => 'Admin login successful.',
                    'token' => $login['token']
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'message' => 'Login successful. Please enter the verification code sent to your email.',
                    'verificationCode' => $login['verificationCode']
                ]);
            }
        } else {
            http_response_code(401);
            echo json_encode(['message' => $login['message']]);
        }
    }

    public function verifyLoginCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        $missingFields = [];
        if (empty($data->email)) {
            $missingFields[] = 'email';
        }
        if (empty($data->verificationCode)) {
            $missingFields[] = 'verificationCode';
        }

        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
            return;
        }

        $verification = $this->authService->verifyLoginCode($data->email, $data->verificationCode);

        if (!empty($verification['token'])) {
            http_response_code(200);
            echo json_encode(['token' => $verification['token']]);
        } else {
            http_response_code(401);
            echo json_encode(['message' => $verification['message']]);
        }
    }

    public function resendCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email is required']);
            return;
        }

        $resend = $this->authService->resendVerificationCode($data->email);

        if ($resend['success']) {
            http_response_code(200);
            echo json_encode(['message' => $resend['message']]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => $resend['message']]);
        }
    }

    public function preRegister() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        $missingFields = [];
        if (empty($data->name)) {
            $missingFields[] = 'name';
        }
        if (empty($data->email)) {
            $missingFields[] = 'email';
        }
        if (empty($data->password)) {
            $missingFields[] = 'password';
        }
        if (empty($data->role_id)) {
            $missingFields[] = 'role_id';
        }

        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
            return;
        }

        $register = $this->authService->preRegister($data->name, $data->email, $data->password, $data->role_id);

        if ($register['success']) {
            http_response_code(201);
            echo json_encode([
                'message' => $register['message'],
                'verificationCode' => $register['verificationCode']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => $register['message']]);
        }
    }

    public function forgotPassword() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email is required']);
            return;
        }

        $response = $this->authService->forgotPassword($data->email);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode($response);
        } else {
            http_response_code(400);
            echo json_encode($response);
        }
    }

    public function verifyForgotPasswordCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        $missingFields = [];
        if (empty($data->email)) {
            $missingFields[] = 'email';
        }
        if (empty($data->code)) {
            $missingFields[] = 'code';
        }

        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
            return;
        }

        $response = $this->authService->verifyForgotPasswordCode($data->email, $data->code);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode($response);
        } else {
            http_response_code(400);
            echo json_encode($response);
        }
    }

    public function resetPassword() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        $missingFields = [];
        if (empty($data->email)) {
            $missingFields[] = 'email';
        }
        if (empty($data->new_password)) {
            $missingFields[] = 'new_password';
        }
        if (empty($data->code)) {
            $missingFields[] = 'code';
        }

        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
            return;
        }

        $response = $this->authService->resetPassword($data->email, $data->new_password, $data->code);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode($response);
        } else {
            http_response_code(400);
            echo json_encode($response);
        }
    }
}
