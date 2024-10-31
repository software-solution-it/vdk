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
        $this->outlookOAuth2Service->initialize($db);
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
            $authorizationUrl = $this->outlookOAuth2Service->getAuthorizationUrl($user_id, $provider_id);
            echo json_encode(['status' => true, 'authorization_url' => $authorizationUrl]);
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
            $accessToken = $this->outlookOAuth2Service->getAccessToken($user_id, $provider_id, $code);
            echo json_encode(['status' => true, 'access_token' => $accessToken['access_token'], 'refresh_token' => $accessToken['refresh_token']]);
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
            $accessToken = $this->outlookOAuth2Service->refreshAccessToken($user_id, $provider_id);
            echo json_encode(['status' => true, 'access_token' => $accessToken['access_token'], 'refresh_token' => $accessToken['refresh_token']]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao atualizar token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao atualizar token de acesso: ' . $e->getMessage()]);
        }
    }


    public function autenticateImap()
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
            // Chama o novo método authenticateImap e obtém os emails
            $emails = $this->outlookOAuth2Service->authenticateImap($user_id, $provider_id);
    
            // Se a autenticação for bem-sucedida, retorne os emails
            echo json_encode(['status' => true, 'message' => 'Authenticated successfully.', 'emails' => $emails]);
    
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao autenticar no IMAP: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao autenticar no IMAP: ' . $e->getMessage()]);
        }
    }
    
    
}
