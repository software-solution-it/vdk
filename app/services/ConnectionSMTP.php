<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ConnectionTesterSmtp {
    public function testSMTPConnection($smtp_host, $smtp_port, $smtp_username, $smtp_password, $encryption) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = $encryption;
            $mail->Port       = $smtp_port;

            $mail->smtpConnect();
            return ['status' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }
}
