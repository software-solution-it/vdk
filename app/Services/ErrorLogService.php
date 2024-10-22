<?php
namespace App\Services;

use App\Models\ErrorLog;

class ErrorLogService {
    private $errorLogModel;

    public function __construct(ErrorLog $errorLogModel) {
        $this->errorLogModel = $errorLogModel;
    }

    public function logError($errorMessage, $file, $line, $userId = null, $additionalInfo = null) {
        return $this->errorLogModel->logError($errorMessage, $file, $line, $userId, $additionalInfo);
    }

    public function getLogsByUserId($userId) {
        return $this->errorLogModel->getLogsByUserId($userId);
    }

    public function getAllLogs() {
        return $this->errorLogModel->getAllLogs();
    }
}
