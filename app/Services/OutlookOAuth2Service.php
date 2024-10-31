<?php
namespace App\Services;
require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use League\OAuth2\Client\Provider\GenericProvider;
use App\Models\Email;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailAccount;

class OutlookOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $oauthProvider;

    public function __construct() {
        // Construtor vazio para flexibilidade na inicialização posterior
    }

    public function initialize($db) {
        // Inicialização dos modelos com o banco de dados
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id) {
        // Cria o estado como uma string JSON codificada em base64
        $state = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));
    
        // Cria a URI de redirecionamento sem o estado embutido
        $redirectUri = 'http://localhost:3000/callback';
    
        // Cria o provedor OAuth com o estado separado
        $this->oauthProvider = new GenericProvider([
            'clientId'                => $emailAccount['client_id'],
            'clientSecret'            => $emailAccount['client_secret'],
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes'                  => 'https://graph.microsoft.com/.default'
        ]);
    
        // Gera a URL de autorização com o estado personalizado
        $authorizationUrl = $this->oauthProvider->getAuthorizationUrl([
            'state' => $state, // Passa o estado personalizado
            'scope' => 'https://graph.microsoft.com/.default'
        ]);
    
        // Retorna a URL para o frontend
        return [
            'status' => true,
            'authorization_url' => $authorizationUrl
        ];
    }
    
    

    public function getAuthorizationUrl($user_id, $provider_id) {
        // Obtém a conta de e-mail pelo user_id e provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Inicializa o OAuthProvider se não estiver configurado
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        // Retorna a URL de autorização
        return $this->oauthProvider->getAuthorizationUrl();
    }

    public function getAccessToken($user_id, $provider_id, $code) {
        // Obtém a conta de e-mail pelo user_id e provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Inicializa o OAuthProvider se não estiver configurado
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        try {
            // Obtém o token de acesso usando o código de autorização
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Atualiza o emailAccount com o novo token e refresh token
            if ($accessToken->getToken() && $accessToken->getRefreshToken()) {
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $accessToken->getToken(),
                    $accessToken->getRefreshToken(),
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );
            }

            return [
                'access_token' => $accessToken->getToken(),
                'refresh_token' => $accessToken->getRefreshToken()
            ];

        } catch (\Exception $e) {
            exit('Failed to get access token: ' . $e->getMessage());
        }
    }

    public function refreshAccessToken($user_id, $provider_id) {
        // Obtém a conta de e-mail pelo user_id e provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Inicializa o OAuthProvider se não estiver configurado
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        try {
            // Obtém um novo token de acesso usando o refresh token
            $newAccessToken = $this->oauthProvider->getAccessToken('refresh_token', [
                'refresh_token' => $emailAccount['refresh_token']
            ]);

            // Atualiza o emailAccount com o novo access token e refresh token (se houver)
            if ($newAccessToken->getToken()) {
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $newAccessToken->getToken(),
                    $newAccessToken->getRefreshToken() ?: $emailAccount['refresh_token'],
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );
            }

            return [
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $emailAccount['refresh_token']
            ];

        } catch (\Exception $e) {
            exit('Failed to refresh access token: ' . $e->getMessage());
        }
    }
}
