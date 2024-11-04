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
            $this->errorLogController->logError("Erro ao obter URL de autorização: " . $e->getMessage(), __FILE__, __LINE__);
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
            $this->errorLogController->logError("Erro ao obter token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
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
            $this->errorLogController->logError("Erro ao atualizar token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Erro ao atualizar token de acesso: ' . $e->getMessage()]);
        }
    }
}
