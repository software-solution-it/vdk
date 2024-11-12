<?php
namespace App\Controllers;

use App\Services\ConnectionIMAP;

class IMAPController {
    protected $connectionIMAPService;

    public function __construct() {
        $this->connectionIMAPService = new ConnectionIMAP();
    }

    public function testConnection() {
        header('Content-Type: application/json');
    
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            $requiredParams = ['user_id', 'email_id'];
            $missingParams = [];
    
            foreach ($requiredParams as $param) {
                if (!isset($data[$param])) {
                    $missingParams[] = $param;
                }
            }
    
            if (!empty($missingParams)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Parâmetros de conexão IMAP incompletos: faltam ' . implode(', ', $missingParams) . '.',
                    'Data' => null
                ]);
                return;
            }
    
            $user_id = $data['user_id'];
            $email_id = $data['email_id'];
    
            $result = $this->connectionIMAPService->testIMAPConnection($user_id, $email_id);
    
            http_response_code($result['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['status'] ? 'Conexão IMAP testada com sucesso.' : 'Falha na conexão IMAP.',
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
