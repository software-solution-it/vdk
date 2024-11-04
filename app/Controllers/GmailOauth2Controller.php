<?php

namespace App\Controllers;

use App\Services\GmailOAuth2Service;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

class GmailOauth2Controller {
    private $gmailOAuth2Service;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->gmailOAuth2Service = new GmailOAuth2Service();
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

    public function listFolders()
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
            $folders = $this->gmailOAuth2Service->listFolders($user_id, $provider_id);
            echo json_encode(['status' => true, 'folders' => $folders]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar pastas: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar pastas: ' . $e->getMessage()]);
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
        $labelIds = isset($data['labelIds']) && is_array($data['labelIds']) ? $data['labelIds'] : [];

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

        if (
            !isset($data['user_id']) ||
            !isset($data['provider_id']) ||
            !isset($data['message_id']) ||
            !isset($data['destination_label_id'])
        ) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, message_id, and destination_label_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $message_id = trim($data['message_id']);
        $destination_label_id = trim($data['destination_label_id']);

        if ($user_id <= 0 || $provider_id <= 0 || empty($message_id) || empty($destination_label_id)) {
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $result = $this->gmailOAuth2Service->moveEmail($user_id, $provider_id, $message_id, $destination_label_id);
            echo json_encode(['status' => true, 'message' => 'E-mail movido com sucesso.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao mover e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao mover e-mail: ' . $e->getMessage()]);
        }
    }

    public function deleteEmail()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (
            !isset($data['user_id']) ||
            !isset($data['provider_id']) ||
            !isset($data['message_id'])
        ) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and message_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $message_id = trim($data['message_id']);

        if ($user_id <= 0 || $provider_id <= 0 || empty($message_id)) {
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $result = $this->gmailOAuth2Service->deleteEmail($user_id, $provider_id, $message_id);
            echo json_encode(['status' => true, 'message' => 'E-mail deletado com sucesso.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao deletar e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao deletar e-mail: ' . $e->getMessage()]);
        }
    }

    public function listEmailsByConversation()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (
            !isset($data['user_id']) ||
            !isset($data['provider_id']) ||
            !isset($data['conversation_id'])
        ) {
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and conversation_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $conversation_id = trim($data['conversation_id']);

        if ($user_id <= 0 || $provider_id <= 0 || empty($conversation_id)) {
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $emails = $this->gmailOAuth2Service->listEmailsByConversation($user_id, $provider_id, $conversation_id);
            echo json_encode(['status' => true, 'emails' => $emails]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar e-mails por conversação: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar e-mails por conversação: ' . $e->getMessage()]);
        }
    }
}
