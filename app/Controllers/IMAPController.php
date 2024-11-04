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

            if (!isset($data['imap_host'], $data['imap_port'], $data['imap_username'], $data['imap_password'])) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Parâmetros de conexão IMAP incompletos.']);
                return;
            }

            $imap_host = $data['imap_host'];
            $imap_port = $data['imap_port'];
            $imap_username = $data['imap_username'];
            $imap_password = $data['imap_password'];

            $result = $this->connectionIMAPService->testIMAPConnection($imap_host, $imap_port, $imap_username, $imap_password);

            http_response_code($result['status'] ? 200 : 500);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        }
    }
}
