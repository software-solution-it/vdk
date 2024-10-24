<?php

namespace App\Controllers;

use App\Services\SMTPService;
use App\Models\SMTPConfig;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class SMTPController {
    private $smtpService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->smtpService = new SMTPService(new SMTPConfig($db));
        $this->errorLogController = new ErrorLogController();
    }

    public function createSMTPConfig() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->user_id) && !empty($data->host) && !empty($data->port) && !empty($data->username) && !empty($data->password) && !empty($data->encryption)) {
            $create = $this->smtpService->createSMTPConfig($data->user_id, $data->host, $data->port, $data->username, $data->password, $data->encryption);

            if ($create) {
                http_response_code(201);
                echo json_encode(['message' => 'SMTP configuration created successfully']);
            } else {
                $this->errorLogController->logError('Failed to create SMTP configuration.', __FILE__, __LINE__);
                http_response_code(500);
                echo json_encode(['message' => 'Failed to create SMTP configuration']);
            }
        } else {
            $this->errorLogController->logError('Incomplete data provided for SMTP configuration creation.', __FILE__, __LINE__);
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
                $this->errorLogController->logError('Failed to update SMTP configuration.', __FILE__, __LINE__);
                http_response_code(500);
                echo json_encode(['message' => 'Failed to update SMTP configuration']);
            }
        } else {
            $this->errorLogController->logError('Incomplete data provided for SMTP configuration update.', __FILE__, __LINE__);
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
                $this->errorLogController->logError('SMTP configuration not found for user ID: ' . $user_id, __FILE__, __LINE__);
                http_response_code(404);
                echo json_encode(['message' => 'SMTP configuration not found']);
            }
        } else {
            $this->errorLogController->logError('User ID is required for fetching SMTP configuration.', __FILE__, __LINE__);
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
                $this->errorLogController->logError('Failed to delete SMTP configuration for ID: ' . $data->id, __FILE__, __LINE__);
                http_response_code(500);
                echo json_encode(['message' => 'Failed to delete SMTP configuration']);
            }
        } else {
            $this->errorLogController->logError('ID is required for deleting SMTP configuration.', __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode(['message' => 'ID is required']);
        }
    }
}
