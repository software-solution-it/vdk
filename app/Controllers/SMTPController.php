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
            $requiredParams = ['email', 'password', 'provider_id', 'recipient', 'html_body'];
            $missingParams = [];

            foreach ($requiredParams as $param) {
                if (!isset($data[$param]) || empty($data[$param])) {
                    $missingParams[] = $param;
                }
            }

            if (!empty($missingParams)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Parâmetros de conexão SMTP incompletos.',
                    'Data' => [
                        'missing_params' => $missingParams
                    ]
                ]);
                return;
            }

            $email = $data['email'];
            $password = $data['password'];
            $provider_id = $data['provider_id'];
            $recipient = $data['recipient'];
            $html_body = $data['html_body'];

            $result = $this->connectionSMTPService->testSMTPConnection(
                $email,
                $password,
                $provider_id,
                $recipient,
                $html_body 
            );

            http_response_code($result['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['status'] ? 'Conexão SMTP testada com sucesso.' : 'Falha na conexão SMTP.',
                'Data' => $result['status'] ? null : $result['message']
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao processar a solicitação: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
}
