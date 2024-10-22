<?php

namespace App\Worker;

require_once __DIR__ . '/../../vendor/autoload.php';
use Exception;
use App\Services\EmailSyncService;
use App\Config\Database;

if ($argc < 3) {
    echo "Usage: php email_sync_worker.php <user_id> <provider_id>\n";
    exit(1);
}

$user_id = intval($argv[1]);
$provider_id = intval($argv[2]);

$database = new Database();
$db = $database->getConnection();

$emailSyncService = new EmailSyncService($db);

echo "Iniciando sincronizacao para user_id=$user_id e provider_id=$provider_id\n";
try {
$emailSyncService->startConsumer($user_id, $provider_id);
} catch (Exception $e) {
    error_log("Erro ao iniciar a sincronizacao: " . $e->getMessage());
    exit(1);
}