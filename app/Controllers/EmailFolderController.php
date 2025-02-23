<?php

namespace App\Controllers;

use App\Services\EmailFolderService;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

class EmailFolderController {
    private $emailFolderService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();

        $this->emailFolderService = new EmailFolderService($db);
        $this->errorLogController = new ErrorLogController();
    }

    public function getFoldersByEmailId($email_id) {
        header('Content-Type: application/json; charset=utf-8');

        if (!$email_id) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'email_id is required.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $folders = $this->emailFolderService->getFoldersByEmailId($email_id);
            
            // Adiciona informações sobre configuração do S3
            $s3Config = [
                'enabled' => !empty(getenv('AWS_ACCESS_KEY_ID')) && 
                           !empty(getenv('AWS_SECRET_ACCESS_KEY')) && 
                           !empty(getenv('AWS_REGION')),
                'region' => getenv('AWS_REGION') ?: null,
                'bucket' => getenv('AWS_BUCKET') ?: 'vdkmail'
            ];

            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Folders retrieved successfully.',
                'Data' => [
                    'folders' => $folders,
                    's3_config' => $s3Config
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Failed to retrieve folders: ' . $e->getMessage(),
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }

}
