<?php 

namespace App\Worker;

require_once __DIR__ . '/../../vendor/autoload.php';

use Exception;
use App\Services\EmailSyncService;
use App\Config\Database;
use App\Controllers\ErrorLogController;

if ($argc < 3) {
    echo "Usage: php email_sync_worker.php <user_id> <email_id>\n";
    exit(1);
}

$user_id = intval($argv[1]);
$email_id = intval($argv[2]);

$database = new Database();
$db = $database->getConnection();
$errorLogController = new ErrorLogController(); 

try {
    $emailSyncService = new EmailSyncService($db);
    echo "Iniciando sincronizacao para user_id=$user_id e email_id=$email_id\n";
    $emailSyncService->startConsumer($user_id, $email_id);
} catch (Exception $e) {
    $errorMessage = "Erro ao iniciar a sincronizacao: " . $e->getMessage();
    error_log($errorMessage);
    $errorLogController->logError($errorMessage, __FILE__, __LINE__, $email_id);
    exit(1);
} finally {
    if ($db) {
        $db = null;
    }
}
