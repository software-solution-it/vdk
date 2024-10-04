<?php

interface AuthServiceInterface {
    public function login($email, $password);
    public function resendVerificationCode($email); // Novo método para reenvio de código de verificação
    public function verifyLoginCode($email, $verificationCode); // Novo método para verificar o código de login
}
