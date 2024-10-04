<?php

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../models/Email.php';
require __DIR__ . '/../services/EmailService.php';

class EmailController {
    private $emailService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailService = new EmailService();
    }
    public function sendEmail() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (
            !isset($data['user_id']) || 
            !isset($data['email_account_id']) || 
            !isset($data['name']) || 
            !isset($data['recipientEmail']) || 
            !isset($data['subject']) || 
            !isset($data['htmlTemplate'])
        ) {
            echo json_encode(['message' => 'Todos os parâmetros são necessários.']);
            http_response_code(400);
            return;
        }

        $user_id = $data['user_id'];
        $recipientEmail = $data['recipientEmail'];
        $subject = $data['subject'];
        $htmlTemplate = $data['htmlTemplate'];

        $result = $this->emailService->sendEmail($user_id,  $recipientEmail, $subject, $htmlTemplate, null);

        if ($result) {
            echo json_encode(['message' => 'E-mail enviado com sucesso.']);
            http_response_code(200);
        } else {
            echo json_encode(['message' => 'Erro ao enviar o e-mail.']);
            http_response_code(500);
        }
    }
}
