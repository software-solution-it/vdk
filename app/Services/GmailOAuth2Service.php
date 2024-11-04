<?php

namespace App\Services;

use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

class GmailOAuth2Service {
    private $gmailOAuth2Service;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->errorLogController = new ErrorLogController();
    }

    public function getAuthorizationUrl()
    {
        header('Content-Type: application/json');
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

        try {
            $result = $this->gmailOAuth2Service->getAuthorizationUrl($user_id, $provider_id);
            echo json_encode($result);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter URL de autorização: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao obter URL de autorização: ' . $e->getMessage()]);
        }
    }

    public function getAccessToken()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id']) || !isset($data['code'])) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and code are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $code = $data['code'];

        if ($user_id <= 0 || $provider_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }

        try {
            $tokens = $this->gmailOAuth2Service->getAccessToken($user_id, $provider_id, $code);
            echo json_encode(['status' => true, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter token de acesso: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao obter token de acesso: ' . $e->getMessage()]);
        }
    }

    public function refreshAccessToken()
    {
        header('Content-Type: application/json');
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

        try {
            $tokens = $this->gmailOAuth2Service->refreshAccessToken($user_id, $provider_id);
            echo json_encode(['status' => true, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao atualizar token de acesso: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao atualizar token de acesso: ' . $e->getMessage()]);
        }
    }

    public function listEmails()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id'])) {
            echo json_encode(['status' => false, 'message' => 'user_id and provider_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $labelIds = isset($data['labelIds']) ? $data['labelIds'] : [];

        if ($user_id <= 0 || $provider_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }

        try {
            $emails = $this->gmailOAuth2Service->listEmails($user_id, $provider_id, $labelIds);
            echo json_encode(['status' => true, 'emails' => $emails]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar e-mails: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar e-mails: ' . $e->getMessage()]);
        }
    }

    public function moveEmail()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id']) || !isset($data['messageId']) || !isset($data['destinationLabelId'])) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, messageId, and destinationLabelId are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $messageId = $data['messageId'];
        $destinationLabelId = $data['destinationLabelId'];

        if ($user_id <= 0 || $provider_id <= 0 || empty($messageId) || empty($destinationLabelId)) {
            echo json_encode(['status' => false, 'message' => 'Invalid input parameters.']);
            return;
        }

        try {
            $result = $this->gmailOAuth2Service->moveEmail($user_id, $provider_id, $messageId, $destinationLabelId);
            echo json_encode(['status' => true, 'message' => 'Email moved successfully.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao mover e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao mover e-mail: ' . $e->getMessage()]);
        }
    }

    public function deleteEmail()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id']) || !isset($data['messageId'])) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and messageId are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $messageId = $data['messageId'];

        if ($user_id <= 0 || $provider_id <= 0 || empty($messageId)) {
            echo json_encode(['status' => false, 'message' => 'Invalid input parameters.']);
            return;
        }

        try {
            $result = $this->gmailOAuth2Service->deleteEmail($user_id, $provider_id, $messageId);
            echo json_encode(['status' => true, 'message' => 'Email deleted successfully.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao deletar e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao deletar e-mail: ' . $e->getMessage()]);
        }
    }

    public function listEmailsByConversation()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id']) || !isset($data['conversationId'])) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and conversationId are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $conversationId = $data['conversationId'];

        if ($user_id <= 0 || $provider_id <= 0 || empty($conversationId)) {
            echo json_encode(['status' => false, 'message' => 'Invalid input parameters.']);
            return;
        }

        try {
            $emails = $this->gmailOAuth2Service->listEmailsByConversation($user_id, $provider_id, $conversationId);
            echo json_encode(['status' => true, 'emails' => $emails]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar e-mails por conversação: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar e-mails por conversação: ' . $e->getMessage()]);
        }
    }
}
