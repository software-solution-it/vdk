<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHandler {
    private $key = "your_secret_key"; 
    private $issuedAt;
    private $expirationTime;
    private $issuer;

    public function __construct() {
        $this->issuedAt = time();
        $this->expirationTime = $this->issuedAt + (60 * 60);
        $this->issuer = "yourdomain.com";
    }

    public function generateToken($userId, $email) {
        $payload = [
            'iat' => $this->issuedAt,
            'exp' => $this->expirationTime,
            'iss' => $this->issuer,
            'data' => [
                'id' => $userId,
                'email' => $email
            ]
        ];

        return JWT::encode($payload, $this->key, 'HS256');
    }

    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
}
?>
