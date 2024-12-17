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
            $requiredParams = ['email', 'password', 'imap_host', 'imap_port', 'encryption'];
            $data = json_decode(file_get_contents('php://input'), true);
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
                    'Message' => 'Parâmetros de conexão IMAP incompletos: faltam ' . implode(', ', $missingParams) . '.',
                    'Data' => null
                ]);
                return;
            }
    
            $email = $data['email'];
            $password = $data['password'];
            $imap_host = $data['imap_host'];
            $imap_port = $data['imap_port'];
            $encryption = $data['encryption'];
    
            $result = $this->connectionIMAPService->testIMAPConnection(
                $email,
                $password,
                $imap_host,
                $imap_port,
                $encryption
            );
    
            // Retorno da resposta
            http_response_code($result['status'] ? 200 : 500);
            echo json_encode([
                'Status' => $result['status'] ? 'Success' : 'Error',
                'Message' => $result['status'] ? 'Conexão IMAP testada com sucesso.' : 'Falha na conexão IMAP.',
                'Data' => $result['status'] ? $result['folders'] : $result['message']
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
