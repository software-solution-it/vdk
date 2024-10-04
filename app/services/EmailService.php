<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../models/EmailAccount.php';
require __DIR__ . '/../services/RabbitMQService.php';

class EmailService {
    private $db;
    private $rabbitMQService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();

        $this->rabbitMQService = new RabbitMQService($this->db);
    }

    public function sendEmailToQueue($user_id, $recipientEmail, $subject, $htmlBody, $plainBody = '') {
        $emailData = [
            'user_id' => $user_id,
            'recipientEmail' => $recipientEmail,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'plainBody' => $plainBody,
        ];

        $this->rabbitMQService->publishMessage('email_queue', $emailData, $user_id);
    }

    public function sendEmail($user_id, $recipientEmail, $subject, $htmlBody, $plainBody = '') {
        $smtpConfigModel = new EmailAccount($this->db);
        $smtpConfig = $smtpConfigModel->getByUserId($user_id);
    
        if (!$smtpConfig || !isset($smtpConfig['smtp_host'], $smtpConfig['smtp_username'], $smtpConfig['smtp_password'], $smtpConfig['smtp_port'])) {
            error_log("Configurações de SMTP não encontradas para o usuário: $user_id");
            return false; 
        }

        $mail = new PHPMailer(true);
    
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpConfig['smtp_host']; 
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpConfig['smtp_username'];
            $mail->Password   = $smtpConfig['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = $smtpConfig['smtp_port'];  
    
            $mail->setFrom($smtpConfig['smtp_username'], 'Seu Nome');
            $mail->addAddress($recipientEmail); 

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail: " . $e->getMessage());
            return false;
        }
    }

    public function sendVerificationEmail($user_id, $email, $code) {
        $subject = 'Your Verification Code';
        $htmlBody = 'Your verification code is: <b>' . $code . '</b>';
        $plainBody = 'Your verification code is: ' . $code;
        
        $this->sendEmailToQueue($user_id, $email, $subject, $htmlBody, $plainBody);
    }
}
