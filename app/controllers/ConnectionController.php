<?php
namespace App\Controllers;

use App\Services\ConnectionSMTP;
use App\Services\ConnectionIMAP;

class ConnectionController {
    private $connectionTesterSmtp;
    private $connectionTesterImap;

    public function __construct() {
        $this->smtpConnection = new ConnectionSMTP();
        $this->imapConnection = new ConnectionIMAP();
    }

    public function testSMTP() {
        $data = json_decode(file_get_contents('php://input'), true);

        $response = $this->connectionTesterSmtp->testSMTPConnection(
            $data['smtp_host'],
            $data['smtp_port'],
            $data['smtp_username'],
            $data['smtp_password'],
            $data['encryption']
        );

        echo json_encode($response);
    }

    public function testIMAP() {
        $data = json_decode(file_get_contents('php://input'), true);

        $response = $this->connectionTesterImap->testIMAPConnection(
            $data['imap_host'],
            $data['imap_port'],
            $data['imap_username'],
            $data['imap_password']
        );

        echo json_encode($response);
    }
}
