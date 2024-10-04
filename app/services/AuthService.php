<?php

include_once __DIR__ . '/../interfaces/AuthServiceInterface.php';
include_once __DIR__ . '/../helpers/PasswordHasher.php';
include_once __DIR__ . '/../services/EmailService.php';
include_once __DIR__ . '/../models/Email.php';
include_once __DIR__ . '/../models/User.php';

class AuthService implements AuthServiceInterface {
    private $user;
    private $jwtHandler;
    private $emailService;

    public function __construct($userModel, $jwtHandler) {
        $this->jwtHandler = $jwtHandler;
        
        $database = new Database();
        $db = $database->getConnection();
        $emailModel = new Email($db); 
        $this->user = new User($db);
        $this->emailService = new EmailService($emailModel);
    }


    public function login($email, $password) {
        $user = $this->user->findByEmail($email);

        if ($user && PasswordHasher::verify($password, $user['password'])) {
                $verificationCode = rand(100000, 999999);
                $expirationTime = time() + (5 * 60); 

                $this->user->updateLoginVerificationCode($user['id'], $verificationCode, $expirationTime);

                $this->emailService->sendVerificationEmail(1, $email, $verificationCode);
                return [
                    'success' => true,
                    'message' => 'Login successful. Please enter the verification code sent to your email.',
                    'verificationCode' => $verificationCode
                ];
        //    } else {
            //    return ['success' => false, 'message' => 'Email not verified.'];
         //   }
        }

        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    public function resendVerificationCode($email) {
        $user = $this->user->findByEmail($email);

        if ($user) {
          //  if ($user['is_verified']) {
                $verificationCode = rand(100000, 999999);
                $expirationTime = time() + (5 * 60);

                $this->user->updateLoginVerificationCode($user['id'], $verificationCode, $expirationTime);

                $emailSent = $this->emailService->sendVerificationEmail(1, $email, $verificationCode);

                if ($emailSent) {
                    return ['success' => true, 'message' => 'Verification code resent successfully.'];
                } else {
                    return ['success' => false, 'message' => 'Failed to resend verification code.'];
                }
           // } else {
            //    return ['success' => false, 'message' => 'Email not verified.'];
           // }
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
        $hashedPassword = PasswordHasher::hash($password);
        
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
