<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailAccount; 
use App\Models\Provider;
use App\Config\Database;
use App\Helpers\EncryptionHelper;

class ConnectionSMTP {
    private $emailAccountModel;
    private $providerModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailAccountModel = new EmailAccount($db); 
        $this->providerModel = new Provider($db);
    }

    public function testSMTPConnection($email, $password, $smtp_host, $smtp_port, $encryption, $recipient, $html_body) {
        $mail = new PHPMailer(true);
    
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $email; 
            $mail->Password   = $password; 
            $mail->SMTPSecure = $encryption; 
            $mail->Port       = $smtp_port;
    
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($email, 'SMTP Test'); 
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test Email';
            $mail->Body    = $html_body;
    
            $mail->smtpConnect();
            $mail->send();
    
            return [
                'status' => true,
                'message' => 'SMTP connection successful, email sent'
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    
}
