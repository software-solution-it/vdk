<?php

namespace App\Worker;

// Incluir o autoloader do Composer
require_once __DIR__ . '/../../vendor/autoload.php'; // Ajuste o caminho conforme necessário
use Exception;
use App\Services\EmailSyncService;
use App\Config\Database;

if ($argc < 3) {
    echo "Usage: php email_sync_worker.php <user_id> <provider_id>\n";
    exit(1);
}

$user_id = intval($argv[1]);
$provider_id = intval($argv[2]);

// Criar uma nova conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Inicializar o serviço de sincronização
$emailSyncService = new EmailSyncService($db);

// Iniciar a sincronização
echo "Iniciando sincronizacao para user_id=$user_id e provider_id=$provider_id\n";
try {
$emailSyncService->startConsumer($user_id, $provider_id);
} catch (Exception $e) {
    error_log("Erro ao iniciar a sincronizacao: " . $e->getMessage());
    exit(1);
}