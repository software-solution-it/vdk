<?php
namespace App\Controllers;

use App\Services\ConnectionSMTP;

class SMTPController {
    protected $connectionSMTPService;

    public function __construct() {
        $this->connectionSMTPService = new ConnectionSMTP();
    }

    public function testConnection() {
        header('Content-Type: application/json');

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $requiredParams = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'encryption'];
            $missingParams = [];

            foreach ($requiredParams as $param) {
                if (!isset($data[$param]) || empty($data[$param])) {
                    $missingParams[] = $param;
                }
            }

            if (!empty($missingParams)) {
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Parâmetros de conexão SMTP incompletos.',
                    'missing_params' => $missingParams
                ]);
                return;
            }

            $smtp_host = $data['smtp_host'];
            $smtp_port = $data['smtp_port'];
            $smtp_username = $data['smtp_username'];
            $smtp_password = $data['smtp_password'];
            $encryption = $data['encryption'];

            $result = $this->connectionSMTPService->testSMTPConnection($smtp_host, $smtp_port, $smtp_username, $smtp_password, $encryption);

            http_response_code($result['status'] ? 200 : 500);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        }
    }
}
