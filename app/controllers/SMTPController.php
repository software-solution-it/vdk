<?php
namespace App\Controllers;

use App\Services\SMTPService;
use App\Models\SMTPConfig;
use App\Config\Database;

class SMTPController {
    private $smtpService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->smtpService = new SMTPService(new SMTPConfig($db));
    }

    public function createSMTPConfig() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->host) && !empty($data->port) && !empty($data->username) && !empty($data->password) && !empty($data->encryption)) {
            $create = $this->smtpService->createSMTPConfig($data->user_id, $data->host, $data->port, $data->username, $data->password, $data->encryption);

            if ($create) {
                http_response_code(201);
                echo json_encode(['message' => 'SMTP configuration created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create SMTP configuration']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }
    public function updateSMTPConfig() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id) && !empty($data->host) && !empty($data->port) && !empty($data->username) && !empty($data->password) && !empty($data->encryption)) {
            $update = $this->smtpService->updateSMTPConfig($data->id, $data->host, $data->port, $data->username, $data->password, $data->encryption);

            if ($update) {
                echo json_encode(['message' => 'SMTP configuration updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to update SMTP configuration']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
    }

    public function getSMTPConfigByUserId($user_id) {
        if ($user_id) {
            $config = $this->smtpService->getSMTPConfigByUserId($user_id);
            if ($config) {
                echo json_encode($config);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'SMTP configuration not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'User ID is required']);
        }
    }

    public function deleteSMTPConfig() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $delete = $this->smtpService->deleteSMTPConfig($data->id);

            if ($delete) {
                echo json_encode(['message' => 'SMTP configuration deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to delete SMTP configuration']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'ID is required']);
        }
    }
}
