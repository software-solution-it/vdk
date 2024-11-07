<?php
namespace App\Controllers;

use App\Services\ErrorLogService;
use App\Config\Database;
use App\Models\ErrorLog;

class ErrorLogController {
    private $errorLogService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->errorLogService = new ErrorLogService(new ErrorLog($db));
    }

    public function logError($errorMessage, $file, $line, $userId = null, $additionalInfo = null) {
        $this->errorLogService->logError($errorMessage, $file, $line, $userId, $additionalInfo);
    }

    public function getLogs() {
        header('Content-Type: application/json');
        try {
            $logs = $this->errorLogService->getAllLogs();
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Logs retrieved successfully.',
                'Data' => $logs
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error retrieving logs: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function getLogsByUserId($userId) {
        header('Content-Type: application/json');
        try {
            $logs = $this->errorLogService->getLogsByUserId($userId);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Logs retrieved successfully for user ID ' . $userId,
                'Data' => $logs
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Error retrieving logs for user ID ' . $userId . ': ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
}
