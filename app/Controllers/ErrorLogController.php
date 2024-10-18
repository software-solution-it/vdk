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
        $logs = $this->errorLogService->getAllLogs();
        echo json_encode($logs);
    }

    public function getLogsByUserId($userId) {
        $logs = $this->errorLogService->getLogsByUserId($userId);
        echo json_encode($logs);
    }
}
