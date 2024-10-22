<?php

namespace App\Services;

use App\Interfaces\AuthServiceInterface;
use App\Helpers\PasswordHasher;
use App\Services\EmailService;
use App\Models\Email;
use App\Models\User;
use App\Config\Database;
use App\Helpers\EncryptionHelper;
class AuthService  {
    private $user;
    private $jwtHandler;
    private $emailService;

    public function __construct($jwtHandler) {
        $this->jwtHandler = $jwtHandler;
        
        $database = new Database();
        $db = $database->getConnection();
        $emailModel = new Email($db); 
        $this->user = new User($db);
        $this->emailService = new EmailService($db);
    }


    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email and password are required.'
            ];
        }
    
        $user = $this->user->findByEmail($email);
    
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
    
        if (!password_verify($password, EncryptionHelper::decrypt($user['password']))) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
    
        if ($user['role_id'] == 1) {
            $token = $this->jwtHandler->generateToken($user['id'], $user['email'], false);
            return [
                'success' => true,
                'role_id' => $user['role_id'],
                'message' => 'Login successful. Here is your token.',
                'token' => $token
            ];
        }
    

        $verificationCode = rand(100000, 999999);
        $expirationTime = time() + (5 * 60);
    
        $updateResult = $this->user->updateLoginVerificationCode($user['id'], $verificationCode, $expirationTime);
        
        if (!$updateResult) {
            return [ 
                'success' => false,
                'message' => 'Failed to update verification code. Please try again later.'
            ];
        }
    
        $emailResult = $this->emailService->sendVerificationEmail($user['id'], $email, $verificationCode);
        
        if (!$emailResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to send verification email: ' . $emailResult['message']
            ];
        }
    
        return [
            'success' => true,
            'role_id' => $user['role_id'],
            'message' => 'Login successful. Please enter the verification code sent to your email.',
            'verificationCode' => $verificationCode
        ];
    }
    
    

    public function resendVerificationCode($email) {
        $user = $this->user->findByEmail($email);

        if ($user) {

                $verificationCode = rand(100000, 999999);
                $expirationTime = time() + (5 * 60);

                $this->user->updateLoginVerificationCode($user['id'], $verificationCode, $expirationTime);

                $emailSent = $this->emailService->sendVerificationEmail(1, $email, $verificationCode);

                if ($emailSent) {
                    return ['success' => true, 'message' => 'Verification code resent successfully.'];
                } else {
                    return ['success' => false, 'message' => 'Failed to resend verification code.'];
                }

        }

        return ['success' => false, 'message' => 'User not found.'];
    }

    public function verifyLoginCode($email, $verificationCode) {
        $user = $this->user->findByEmail($email);

        if ($user) {
            if ($user['verification_code'] === $verificationCode && time() <= strtotime($user['code_expiration'])) {
                return ['token' => $this->jwtHandler->generateToken($user['id'], $user['email'])];
            } else {
                return ['message' => 'Invalid or expired verification code.'];
            }
        }

        return ['message' => 'User not found.'];
    }

    public function preRegister($name, $email, $password, $role_id) {
        $hashedPassword = EncryptionHelper::encrypt($password);
        
        $verificationCode = rand(100000, 999999);
    
        $expirationTime = time() + (5 * 60);
        $expirationDateTime = date('Y-m-d H:i:s', $expirationTime);
    
        $user_id = $this->user->create($name, $email, $hashedPassword, $verificationCode, $expirationDateTime, $role_id);
    
        if ($user_id) {
            $emailSent = $this->emailService->sendVerificationEmail(1, $email, $verificationCode);
    
            if ($emailSent) {
                return [
                    'success' => true,
                    'message' => 'User registered successfully. Please check your email for the verification code.',
                    'verificationCode' => $verificationCode,
                    'expiration' => $expirationDateTime
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to send verification email.'];
            }
        }
    
        return ['success' => false, 'message' => 'Failed to register user.'];
    }


    public function forgotPassword($email) {
        $user = $this->user->findByEmail($email);
    
        if ($user) {
            $forgotPasswordCode = rand(100000, 999999);
    
            $expirationTime = time() + (5 * 60);
            $expirationDateTime = date('Y-m-d H:i:s', $expirationTime);
    
            $this->user->updateForgotPasswordCode($email, $forgotPasswordCode, $expirationDateTime);
    
            $emailSent = $this->emailService->sendVerificationEmail(1, $email, $forgotPasswordCode);
    
            if ($emailSent) {
                return [
                    'success' => true,
                    'message' => 'Forgot password code sent. Please check your email.',
                    'forgotPasswordCode' => $forgotPasswordCode, 
                    'expiration' => $expirationDateTime 
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to send forgot password code.'];
            }
        }
    
        return ['success' => false, 'message' => 'Email not found.'];
    }


    public function verifyForgotPasswordCode($email, $code) {
        $user = $this->user->findByEmail($email);
    
        if ($user) {
            if ($user['forgot_password_code'] == $code && time() <= strtotime($user['forgot_password_expiration'])) {
                return [
                    'success' => true,
                    'message' => 'Verification code is valid.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification code.'
                ];
            }
        }
    
        return [
            'success' => false,
            'message' => 'User not found.'
        ];
    }


    public function resetPassword($email, $newPassword, $code) {
        $verificationResult = $this->verifyForgotPasswordCode($email, $code);
    
        if ($verificationResult['success']) {
            $hashedPassword = PasswordHasher::hash($newPassword);
    
            $passwordUpdated = $this->user->updatePassword($email, $hashedPassword);
    
            if ($passwordUpdated) {
                $this->user->clearForgotPasswordCode($email);
    
                return [
                    'success' => true,
                    'message' => 'Password reset successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to reset password.'
                ];
            }
        }
    
        return $verificationResult;
    }
    
    
}
