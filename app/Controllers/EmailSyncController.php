<?php

namespace App\Controllers;

use App\Services\EmailSyncService;
use App\Config\Database;
use App\Controllers\ErrorLogController; // Importação da classe ErrorLogController
use Exception;

class EmailSyncController {
    private $emailSyncService;
    private $errorLogController; // Adicionando a propriedade para o errorLogController

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();

        $this->emailSyncService = new EmailSyncService($db);
        $this->errorLogController = new ErrorLogController(); // Instanciando o ErrorLogController
    }

    public function startConsumer()
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
    
        // Log para saber que o consumo está prestes a ser iniciado
        $this->errorLogController->logError("Iniciando a sincronização para user_id: $user_id e provider_id: $provider_id", __FILE__, __LINE__);
    
        $command = "php /home/suporte/vdk/app/Worker/email_sync_worker.php $user_id $provider_id 2>&1"; // Captura de erro
    
        // Executa o comando e captura a saída
        $output = shell_exec($command);
    
        // Log da saída do comando
        $this->errorLogController->logError("Saída do comando: $output", __FILE__, __LINE__);
    
        // Retorna uma resposta indicando que a sincronização foi iniciada
        echo json_encode(['status' => true, 'message' => 'Sincronização de e-mails iniciada em segundo plano.']);
    }

    public function oauthCallback()
    {
        // Obtenha os dados do payload JSON
        $input = json_decode(file_get_contents('php://input'), true);
    
        $code = $input['code'] ?? null;
        $state = $input['state'] ?? null;
        $userId = $input['userId'] ?? null; // Captura o userId do payload
    
        if (!$code || !$state) {
            $this->errorLogController->logError('Código de autorização ou estado não fornecido.', __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Código de autorização ou estado não fornecido.']);
            return;
        }
    
        // Decodifica o estado para obter user_id e provider_id
        $stateData = json_decode(base64_decode($state), true);
        
        if (!isset($stateData['user_id']) || !isset($stateData['provider_id'])) {
            $this->errorLogController->logError('Estado inválido: ' . $state, __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Estado inválido.']);
            return;
        }
    
        $providerId = $stateData['provider_id'];
    
        // Recupera a conta de e-mail associada ao user_id e provider_id
        $emailAccount = $this->emailSyncService->getEmailAccountByUserIdAndProviderId($userId, $providerId);
    
        if ($emailAccount) {
            // Solicita um novo token OAuth2 usando o código
            try {
                $this->emailSyncService->requestNewOAuthToken($emailAccount, $code);
                echo json_encode(['status' => true, 'message' => 'Autorização concluída com sucesso!']);
            } catch (Exception $e) {
                $this->errorLogController->logError("Erro ao solicitar token: " . $e->getMessage(), __FILE__, __LINE__);
                echo json_encode(['status' => false, 'message' => 'Erro ao completar a autorização: ' . $e->getMessage()]);
            }
        } else {
            $this->errorLogController->logError('Conta de e-mail não encontrada para user_id: ' . $userId . ' e provider_id: ' . $providerId, __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Conta de e-mail não encontrada.']);
        }
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
    
        $emailAccount = $this->emailSyncService->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
    
        // Verificar se a conta de e-mail foi encontrada
        if (!$emailAccount) {
            $this->errorLogController->logError("Email account not found for user_id: $user_id and provider_id: $provider_id", __FILE__, __LINE__);
            echo json_encode(['status' => false, 'message' => 'Email account not found.']);
            return;
        }
    
        // Obter a URL de autorização
        $authorizationUrl = $this->emailSyncService->getAuthorizationUrl($emailAccount);
    
        // Retornar a URL de autorização como JSON
        echo json_encode(['status' => true, 'authorization_url' => $authorizationUrl]);
    }
}
