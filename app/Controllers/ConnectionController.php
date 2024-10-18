<?php
namespace App\Controllers;

use App\Services\ConnectionSMTP;
use App\Services\ConnectionIMAP;
use App\Controllers\ErrorLogController;

class ConnectionController {
    private $connectionTesterSmtp;
    private $connectionTesterImap;
    private $errorLogController;

    public function __construct() {
        $this->connectionTesterSmtp = new ConnectionSMTP();
        $this->connectionTesterImap = new ConnectionIMAP();
        $this->errorLogController = new ErrorLogController();
    }

    public function testSMTP() {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $response = $this->connectionTesterSmtp->testSMTPConnection(
                $data['smtp_host'],
                $data['smtp_port'],
                $data['smtp_username'],
                $data['smtp_password'],
                $data['encryption']
            );
            echo json_encode($response);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao testar conexão SMTP: ' . $e->getMessage()]);
        }
    }

    public function testIMAP() {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $response = $this->connectionTesterImap->testIMAPConnection(
                $data['imap_host'],
                $data['imap_port'],
                $data['imap_username'],
                $data['imap_password']
            );
            echo json_encode($response);
        } catch (\Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao testar conexão IMAP: ' . $e->getMessage()]);
        }
    }
}
