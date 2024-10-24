<?php

namespace App\Controllers;

use App\Services\EmailSyncService;
use App\Config\Database;

class EmailSyncController {
    private $emailSyncService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();

        $this->emailSyncService = new EmailSyncService($db);
    }
    public function startConsumer()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id'])) {
            echo json_encode(['status' => false, 'message' => 'user_id and provider_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);

        if ($user_id <= 0 || $provider_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }

        $command = "php /home/suporte/vdk/app/Worker/email_sync_worker.php $user_id $provider_id > /dev/null 2>&1 &";

        shell_exec($command);

        echo json_encode(['status' => true, 'message' => 'Sincronização de e-mails iniciada em segundo plano.']);
    }


    public function oauthCallback()
{
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if ($code && $state) {
        $stateData = json_decode(base64_decode($state), true);
        $userId = $stateData['user_id'];
        $providerId = $stateData['provider_id'];

        $emailAccount = $this->emailSyncService->getEmailAccountByUserIdAndProviderId($userId, $providerId);

        if ($emailAccount) {
            $this->emailSyncService->requestNewOAuthToken($emailAccount, $code);

            echo "Autorização concluída com sucesso!";
        } else {
            echo "Conta de e-mail não encontrada.";
        }
    } else {
        echo "Código de autorização não fornecido.";
    }
}
}
