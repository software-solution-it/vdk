<?php
require_once 'vendor/autoload.php'; 

use App\Config\Database;
use App\Services\EmailSyncService;

if ($argc < 3) {
    echo "Uso: php email_consumer.php <user_id> <provider_id>\n";
    exit(1);
}

$user_id = $argv[1];
$provider_id = $argv[2];

$database = new Database();
$db = $database->getConnection();

$emailSyncService = new EmailSyncService($db);
$queue_name = $emailSyncService->generateQueueName($user_id, $provider_id);
$emailSyncService->consumeEmailSyncQueue($user_id, $provider_id, $queue_name);