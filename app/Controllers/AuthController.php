<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\JWTHandler;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class AuthController {
    private $authService;
    private $errorLogController;

    public function __construct() {
        $this->authService = new AuthService(new JWTHandler());
        $this->errorLogController = new ErrorLogController(); 
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
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }

        $login = $this->authService->login($data->email, $data->password);

        if ($login['success']) {
            http_response_code(200);
            $message = ($login['role_id'] == 1) ? 'Admin login successful.' : 'Login successful. Please enter the verification code sent to your email.';
            echo json_encode([
                'Status' => 'Success',
                'Message' => $message,
                'Data' => [
                    'token' => $login['token'],
                    'verificationCode' => $login['verificationCode'] ?? null
                ]
            ]);
        } else {
            $this->errorLogController->logError($login['message'], __FILE__, __LINE__);
            http_response_code(401);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $login['message'],
                'Data' => null
            ]);
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
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }

        $verification = $this->authService->verifyLoginCode($data->email, $data->verificationCode);

        if (!empty($verification['token'])) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Verification successful.',
                'Data' => ['token' => $verification['token']]
            ]);
        } else {
            $this->errorLogController->logError($verification['message'], __FILE__, __LINE__);
            http_response_code(401);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $verification['message'],
                'Data' => null
            ]);
        }
    }

    public function resendCode() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Email is required',
                'Data' => null
            ]);
            return;
        }

        $resend = $this->authService->resendVerificationCode($data->email);

        if ($resend['success']) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => $resend['message'],
                'Data' => null
            ]);
        } else {
            $this->errorLogController->logError($resend['message'], __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $resend['message'],
                'Data' => null
            ]);
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
                'Status' => 'Error',
                'Message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }

        $register = $this->authService->preRegister($data->name, $data->email, $data->password, $data->role_id);

        if ($register['success']) {
            http_response_code(201);
            echo json_encode([
                'Status' => 'Success',
                'Message' => $register['message'],
                'Data' => ['verificationCode' => $register['verificationCode']]
            ]);
        } else {
            $this->errorLogController->logError($register['message'], __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $register['message'],
                'Data' => null
            ]);
        }
    }

    public function forgotPassword() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Email is required',
                'Data' => null
            ]);
            return;
        }

        $response = $this->authService->forgotPassword($data->email);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => $response['message'],
                'Data' => null
            ]);
        } else {
            $this->errorLogController->logError($response['message'], __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $response['message'],
                'Data' => null
            ]);
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
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }

        $response = $this->authService->verifyForgotPasswordCode($data->email, $data->code);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Verification code is valid.',
                'Data' => null
            ]);
        } else {
            $this->errorLogController->logError($response['message'], __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $response['message'],
                'Data' => null
            ]);
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
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }

        $response = $this->authService->resetPassword($data->email, $data->new_password, $data->code);

        if ($response['success']) {
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Password reset successfully.',
                'Data' => null
            ]);
        } else {
            $this->errorLogController->logError($response['message'], __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $response['message'],
                'Data' => null
            ]);
        }
    }
}
