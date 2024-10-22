<?php
namespace App\Controllers;
use App\Services\EmailSyncService;
use Exception;
include __DIR__.'/vendor/autoload.php'; 

use App\Services\EmailAccountService; 
use Webklex\PHPIMAP\ClientManager;
use App\Config\Database;

class ImapTestController
{
    private $emailAccountService;

    public function __construct()
    {   
        
        $database = new Database();
        $db = $database->getConnection();
         
        $this->emailAccountService = new EmailSyncService($db); // Inicializa o serviço de conta de e-mail
    }

    public function testImap()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id']) || !isset($data['provider_id'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => false, 'message' => 'User ID and Provider ID are required']);
            return;
        }

        $user_id = $data['user_id'];
        $provider_id = $data['provider_id'];

        // Obter a conta de e-mail usando o EmailAccountService
        $emailAccount = $this->emailAccountService->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);

        if (!$emailAccount) {
            echo json_encode(['status' => false, 'message' => 'Email account not found']);
            return;
        }

        if (empty($emailAccount['auth_code'])) {
            $auth_url = $this->getAuthorizationUrl($emailAccount['client_id'], $emailAccount['tenant_id']); // Gera a URL de autorização
            echo json_encode([
                'status' => 'consent_required',
                'auth_url' => $auth_url,
                'message' => 'Consent code is missing. Please authenticate.'
            ]);
            return;
        }

        $access_token = $emailAccount['oauth_token']; 

        $cm = new ClientManager();

        $client = $cm->make([
            'host'          => 'outlook.office365.com',
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => false,
            'username'      => $emailAccount['email'],
            'password'      => $access_token, // Aqui usamos o token OAuth2
            'protocol'      => 'imap',
            'authentication'=> 'oauth',       // Configurar para usar OAuth2
        ]);

        try {
            $client->connect();
            
            $folder = $client->getFolder('INBOX');
            $all_messages = $folder->query()->all()->get();

            echo json_encode([
                'status' => 'success',
                'message_count' => count($all_messages)
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function getAuthorizationUrl($client_id, $tenant_id)
    {
        $redirect_uri = urlencode('https://seusite.com/callback');
        $scope = urlencode('https://graph.microsoft.com/Mail.Read offline_access'); 

        return "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/authorize?client_id=$client_id&response_type=code&redirect_uri=$redirect_uri&scope=$scope";
    }
}
