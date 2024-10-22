<?php
namespace App\Helpers;

use App\Services\UserService;
use App\Helpers\JWTHandler;
use App\Models\User;
use App\Config\Database;

class AuthMiddleware {
    public static function verifyBearerToken() {
        $publicRoutes = [
            '/auth/login',
            '/auth/register',
            '/auth/forgot-password',
            '/auth/verify-code',
            '/auth/reset-password',
            '/auth/resend-code',
        ];

        $request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        if (in_array($request_uri, $publicRoutes)) {
            return; 
        }

        $headers = getallheaders();

        error_log("Headers recebidos: " . print_r($headers, true));

        $authHeader = null;
        foreach ($headers as $key => $value) {
            error_log("Header: $key => $value");

            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(['message' => 'Authorization header not found']);
            exit();
        }

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid Authorization header format']);
            exit();
        }

        $token = $matches[1];
        $jwtHandler = new JWTHandler();

        $decoded = $jwtHandler->validateToken($token);

        if (!$decoded) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid or expired token']);
            exit();
        }

        $user_id = $decoded->data->id;

        $database = new Database();
        $db = $database->getConnection();
        $userService = new UserService(new User($db));

        $hasAccess = $userService->checkUserAccess($user_id, $request_uri);

        if (!$hasAccess) {
            http_response_code(403);
            echo json_encode(['message' => 'Access denied']);
            exit();
        }
    }
}
