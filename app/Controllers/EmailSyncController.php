<?php
namespace App\Controllers;

use App\Services\EmailSyncService;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class EmailSyncController {
    private $emailSyncService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailSyncService = new EmailSyncService($db);
        $this->errorLogController = new ErrorLogController();
    }

    public function startConsumer() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id'])) {
            $this->errorLogController->logError('user_id and provider_id are required.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'user_id and provider_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);

        if ($user_id <= 0 || $provider_id <= 0) {
            $this->errorLogController->logError('Invalid user_id or provider_id.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }

        // Tenta obter o token OAuth2
        $tokenResponse = $this->emailSyncService->getOAuth2Token($user_id, $provider_id, null);
        if (!$tokenResponse['status']) {
            echo json_encode($tokenResponse);
            return;
        }

        // Inicia o consumidor para sincronizar e-mails
        $command = "php /home/suporte/vdk/app/Worker/email_sync_worker.php $user_id $provider_id > /dev/null 2>&1 &";
        shell_exec($command);
        echo json_encode(['status' => true, 'message' => 'Sincronização de e-mails iniciada em segundo plano.']);
    }
}
