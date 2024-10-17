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

    foreach ($publicRoutes as $route) {
        if (strpos($request_uri, $route) !== false) {
            return;
        }
    }


        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(['message' => 'Authorization header not found']);
            exit();
        }

        $authHeader = $headers['Authorization'];
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

        $parsedUrl = parse_url($request_uri);
        $path = $parsedUrl['path'];

        $database = new Database();
        $db = $database->getConnection();
        
        $userService = new UserService(new User($db));

        $hasAccess = $userService->checkUserAccess($user_id, $path);

        if (!$hasAccess) {
            http_response_code(403);
            echo json_encode(['message' => 'Access denied']);
            exit();
        }
    }
}
