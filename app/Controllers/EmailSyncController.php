<?php

namespace App\Controllers;

use App\Services\EmailSyncService;
use App\Config\Database;
use App\Controllers\ErrorLogController; 
use Exception;

class EmailSyncController {
    private $emailSyncService;
    private $errorLogController; 

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();

        $this->emailSyncService = new EmailSyncService($db);
        $this->errorLogController = new ErrorLogController();
    }

    public function startConsumer()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $data = json_decode(file_get_contents('php://input'), true);
    
        if (!isset($data['user_id']) || !isset($data['email_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id and email_id are required.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    
        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);
    
        if ($user_id <= 0 || $email_id <= 0) {
            $this->errorLogController->logError('Invalid user_id or email_id.', __FILE__, __LINE__);
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id or email_id.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    
        // Caminho do arquivo de lock
        $lockFile = '/tmp/email_sync_' . $user_id . '_' . $email_id . '.lock';
    
        // Verificar se o arquivo de lock já existe
        if (file_exists($lockFile)) {
            http_response_code(409); // Conflito: já existe uma sincronização em andamento
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'A sincronização já está em andamento para este usuário e e-mail.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    
        // Criar o arquivo de lock
        file_put_contents($lockFile, 'lock');
    
        try {
            $command = sprintf(
                'php %s %d %d > /dev/null 2>&1 &',
                escapeshellarg('/home/suporte/vdk/app/Worker/email_sync_worker.php'),
                $user_id,
                $email_id
            );
    
            exec($command);
    
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Sincronização de e-mails iniciada em segundo plano.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            // Remover o arquivo de lock em caso de falha
            unlink($lockFile);
    
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Falha ao iniciar a sincronização de e-mails.',
                'Data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
