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
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['email_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id and email_id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);

        if ($user_id <= 0 || $email_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id or email_id.',
                'Data' => null
            ]);
            return;
        }

        try {
            $result = $this->outlookOAuth2Service->getAuthorizationUrl($user_id, $email_id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Authorization URL retrieved successfully.',
                'Data' => $result
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter URL de autorização: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao obter URL de autorização: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function getAccessToken()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['email_id']) || !isset($data['code'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id, email_id, and code are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);
        $code = $data['code'];

        if ($user_id <= 0 || $email_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id or email_id.',
                'Data' => null
            ]);
            return;
        }

        try {
            $tokens = $this->outlookOAuth2Service->getAccessToken($user_id, $email_id, $code);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Access token retrieved successfully.',
                'Data' => [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token']
                ]
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao obter token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao obter token de acesso: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function refreshAccessToken()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['email_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id and email_id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);

        if ($user_id <= 0 || $email_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id or email_id.',
                'Data' => null
            ]);
            return;
        }

        try {
            $tokens = $this->outlookOAuth2Service->refreshAccessToken($user_id, $email_id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Access token refreshed successfully.',
                'Data' => [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token']
                ]
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao atualizar token de acesso: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao atualizar token de acesso: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function moveEmail()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['email_id'], $data['conversation_Id'], $data['destination_folder_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id, email_id, conversation_Id, and destination_folder_id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);
        $messageId = $data['conversation_Id'];
        $destinationFolderId = $data['destination_folder_id'];

        if ($user_id <= 0 || $email_id <= 0 || empty($messageId) || empty($destinationFolderId)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid parameters.',
                'Data' => null
            ]);
            return;
        }

        try {
            $this->outlookOAuth2Service->moveEmail($user_id, $email_id, $messageId, $destinationFolderId);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Email moved successfully.',
                'Data' => null
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao mover o e-mail: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao mover o e-mail: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function deleteEmail()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['email_id'], $data['conversation_Id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id, email_id, and conversation_Id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);
        $messageId = $data['conversation_Id'];

        if ($user_id <= 0 || $email_id <= 0 || empty($messageId)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid parameters.',
                'Data' => null
            ]);
            return;
        }

        try {
            $this->outlookOAuth2Service->deleteEmail($user_id, $email_id, $messageId);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Email deleted successfully.',
                'Data' => null
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao deletar o e-mail: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao deletar o e-mail: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function listFolders()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['email_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id and email_id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);

        if ($user_id <= 0 || $email_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id or email_id.',
                'Data' => null
            ]);
            return;
        }

        try {
            $folders = $this->outlookOAuth2Service->listFolders($user_id, $email_id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Folders listed successfully.',
                'Data' => $folders
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar pastas: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao listar pastas: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function listEmailsByConversation()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'], $data['email_id'], $data['conversation_id'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'user_id, email_id, and conversation_id are required.',
                'Data' => null
            ]);
            return;
        }

        $user_id = intval($data['user_id']);
        $email_id = intval($data['email_id']);
        $conversation_id = $data['conversation_id'];

        if ($user_id <= 0 || $email_id <= 0 || empty($conversation_id)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Invalid user_id, email_id, or conversation_id.',
                'Data' => null
            ]); 
            return;
        }

        try {
            $emails = $this->outlookOAuth2Service->listEmailsByConversation($user_id, $conversation_id);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Emails retrieved successfully.',
                'Data' => $emails
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao listar e-mails por conversa: " . $e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao listar e-mails por conversa: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
}
