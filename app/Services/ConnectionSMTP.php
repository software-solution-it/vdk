<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailAccount; 
use App\Config\Database;

class ConnectionSMTP {
    private $emailAccountModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailAccountModel = new EmailAccount($db); 
    }

    public function testSMTPConnection($user_id, $email_id, $recipient, $html_body) {
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
        
        if (!$emailAccount) {
            return ['status' => false, 'message' => 'Email account not found'];
        }

        $smtp_host = $emailAccount['smtp_host'];
        $smtp_port = $emailAccount['smtp_port'];
        $smtp_password = $emailAccount['password']; 
        $encryption = $emailAccount['encryption'] ?? 'tls';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = $encryption;
            $mail->Port       = $smtp_port;

            $mail->setFrom("Teste SMTP"); 
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test Email';
            $mail->Body    = $html_body;

            $mail->smtpConnect(); 
            $mail->send();

            return ['status' => true, 'message' => 'SMTP connection successful, email sent'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }
}
