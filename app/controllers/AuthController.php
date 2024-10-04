<?php
include_once __DIR__ . '/../services/AuthService.php';
include_once __DIR__ . '/../helpers/JWTHandler.php';
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';

class AuthController {
    private $authService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->authService = new AuthService(new User($db), new JWTHandler());
    }
    public function login() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email) && !empty($data->password)) {
            $login = $this->authService->login($data->email, $data->password);

            if ($login['success']) {
                http_response_code(200);
                echo json_encode([
                    'message' => $login['message'],
                    'verificationCode' => $login['verificationCode'] 
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['message' => $login['message']]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }


    public function verifyLoginCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email) && !empty($data->verificationCode)) {
            $verification = $this->authService->verifyLoginCode($data->email, $data->verificationCode);

            if (!empty($verification['token'])) {
                http_response_code(200);
                echo json_encode(['token' => $verification['token']]);
            } else {
                http_response_code(401);
                echo json_encode(['message' => $verification['message']]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }

    public function resendCode() {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email)) {
            $resend = $this->authService->resendVerificationCode($data->email);

            if ($resend['success']) {
                http_response_code(200);
                echo json_encode(['message' => $resend['message']]);
            } else {
                http_response_code(500);
                echo json_encode(['message' => $resend['message']]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email is required']);
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
            echo json_encode([
                'message' => 'Missing required fields: ' . implode(', ', $missingFields)
            ]);
            return;
        }
    

        $register = $this->authService->preRegister($data->name, $data->email, $data->password, role_id: $data->role_id);
    
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
    
        if (!empty($data->email)) {
            $response = $this->authService->forgotPassword($data->email);
    
            if ($response['success']) {
                http_response_code(200);
                echo json_encode($response);
            } else {
                http_response_code(400);
                echo json_encode($response);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email is required']);
        }
    }

    public function verifyForgotPasswordCode() {
        header('Content-Type: application/json');
    
        $data = json_decode(file_get_contents("php://input"));
    
        if (!empty($data->email) && !empty($data->code)) {
            $response = $this->authService->verifyForgotPasswordCode($data->email, $data->code);
    
            if ($response['success']) {
                http_response_code(200);
                echo json_encode($response);
            } else {
                http_response_code(400);
                echo json_encode($response);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email and code are required']);
        }
    }

    public function resetPassword() {
        header('Content-Type: application/json');
    
        $data = json_decode(file_get_contents("php://input"));
    
        if (!empty($data->email) && !empty($data->new_password) && !empty($data->code)) {

            $response = $this->authService->resetPassword($data->email, $data->new_password, $data->code);
    
            if ($response['success']) {
                http_response_code(200);
                echo json_encode($response);
            } else {
                http_response_code(400);
                echo json_encode($response);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Email, new password, and verification code are required']);
        }
    }
    
    
    
    
}
