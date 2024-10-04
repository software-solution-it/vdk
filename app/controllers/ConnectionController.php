<?php

include_once __DIR__ . '/../services/ConnectionSMTP.php';
include_once __DIR__ . '/../services/ConnectionIMAP.php';

class ConnectionController {
    private $connectionTesterSmtp;
    private $connectionTesterImap;

    public function __construct() {
        $this->connectionTesterSmtp = new ConnectionTesterSmtp();
        $this->connectionTesterImap = new ConnectionTesterImap();
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
