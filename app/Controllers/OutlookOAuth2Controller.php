<?php

namespace App\Controllers;

use App\Services\OutlookOAuth2Service;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

class OutlookOAuth2Controller {
    private $outlookOAuth2Service;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->outlookOAuth2Service = new OutlookOAuth2Service();
        $this->errorLogController = new ErrorLogController();
    }

    public function getAuthorizationUrl()
    {
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

        try {
            $result = $this->outlookOAuth2Service->getAuthorizationUrl($user_id, $provider_id);
            echo json_encode($result);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter URL de autorização: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao obter URL de autorização: ' . $e->getMessage()]);
        }
    }

    public function getAccessToken()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id']) || !isset($data['code'])) {
            $this->errorLogController->logError('user_id, provider_id, and code are required.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and code are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $code = $data['code'];

        if ($user_id <= 0 || $provider_id <= 0) {
            $this->errorLogController->logError('Invalid user_id or provider_id.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Invalid user_id or provider_id.']);
            return;
        }

        try {
            $tokens = $this->outlookOAuth2Service->getAccessToken($user_id, $provider_id, $code);
            echo json_encode(['status' => true, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao obter token de acesso: ' . $e->getMessage()]);
        }
    }

    public function refreshAccessToken()
    {
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

        try {
            $tokens = $this->outlookOAuth2Service->refreshAccessToken($user_id, $provider_id);
            echo json_encode(['status' => true, 'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao atualizar token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao atualizar token de acesso: ' . $e->getMessage()]);
        }
    }

    public function authenticateImap()
    {
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

        try {
            $result = $this->outlookOAuth2Service->authenticateImap($user_id, $provider_id);
            echo json_encode(['status' => true, 'message' => 'Authenticated successfully.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao autenticar no IMAP: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao autenticar no IMAP: ' . $e->getMessage()]);
        }
    }

    public function moveEmail()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['provider_id'], $data['message_id'], $data['destination_folder_id'])) {
            $this->errorLogController->logError('user_id, provider_id, message_id, and destination_folder_id are required.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, message_id, and destination_folder_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $messageId = $data['message_id'];
        $destinationFolderId = $data['destination_folder_id'];

        if ($user_id <= 0 || $provider_id <= 0 || empty($messageId) || empty($destinationFolderId)) {
            $this->errorLogController->logError('Invalid parameters.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $this->outlookOAuth2Service->moveEmail($user_id, $provider_id, $messageId, $destinationFolderId);
            echo json_encode(['status' => true, 'message' => 'Email moved successfully.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao mover o e-mail: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao mover o e-mail: ' . $e->getMessage()]);
        }
    }

    public function deleteEmail()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['provider_id'], $data['message_id'])) {
            $this->errorLogController->logError('user_id, provider_id, and message_id are required.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and message_id are required.']);
            return;
        }

        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $messageId = $data['message_id'];

        if ($user_id <= 0 || $provider_id <= 0 || empty($messageId)) {
            $this->errorLogController->logError('Invalid parameters.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        try {
            $this->outlookOAuth2Service->deleteEmail($user_id, $provider_id, $messageId);
            echo json_encode(['status' => true, 'message' => 'Email deleted successfully.']);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao deletar o e-mail: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao deletar o e-mail: ' . $e->getMessage()]);
        }
    }

    public function listFolders()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['provider_id'])) {
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

        try {
            $folders = $this->outlookOAuth2Service->listFolders($user_id, $provider_id);
            echo json_encode(['status' => true, 'folders' => $folders]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar pastas: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar pastas: ' . $e->getMessage()]);
        }
    }

    public function listEmailsByConversation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
    
        if (!isset($data['user_id'], $data['provider_id'], $data['conversation_id'])) {
            $this->errorLogController->logError('user_id, provider_id, and conversation_id are required.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'user_id, provider_id, and conversation_id are required.']);
            return;
        }
    
        $user_id = intval($data['user_id']);
        $provider_id = intval($data['provider_id']);
        $conversation_id = $data['conversation_id'];
    
        if ($user_id <= 0 || $provider_id <= 0 || empty($conversation_id)) {
            $this->errorLogController->logError('Invalid user_id, provider_id, or conversation_id.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Invalid user_id, provider_id, or conversation_id.']);
            return;
        }
    
        try {
            $emails = $this->outlookOAuth2Service->listEmailsByConversation($user_id, $provider_id, $conversation_id);
            echo json_encode(['status' => true, 'emails' => $emails]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar e-mails por conversa: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao listar e-mails por conversa: ' . $e->getMessage()]);
        }
    }
    
}
