<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Email;
use App\Models\EmailAccount;
use Exception;

class OutlookOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $httpClient;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes = [
        'offline_access',
        'User.Read',
        'Mail.Read',
        'Mail.Send',
        'IMAP.AccessAsUser.All',
        'SMTP.Send'
    ];

    public function __construct() {
        $this->httpClient = new Client();
    }

    public function initialize($db) {
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthParameters($emailAccount, $user_id, $provider_id) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];

        // Encode user_id and provider_id
        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

        $this->redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        // Get the email account
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

        $authorizationUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'
            . http_build_query([
                'client_id' => $this->clientId,
                'response_type' => 'code',
                'redirect_uri' => $this->redirectUri,
                'scope' => implode(' ', $this->scopes),
                'response_mode' => 'query',
                'state' => base64_encode(random_bytes(10))
            ]);

        return [
            'status' => true,
            'authorization_url' => $authorizationUrl
        ];
    }

    public function getAccessToken($user_id, $provider_id, $code) {
        // Get the email account
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

        try {
            $response = $this->httpClient->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                    'scope' => implode(' ', $this->scopes)
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['access_token']) && isset($body['refresh_token'])) {
                // Update the email account with new tokens
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $body['access_token'],
                    $body['refresh_token'],
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );

                return [
                    'access_token' => $body['access_token'],
                    'refresh_token' => $body['refresh_token']
                ];
            } else {
                throw new Exception('Access token or refresh token not found in response');
            }

        } catch (RequestException $e) {
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        }
    }

    public function refreshAccessToken($user_id, $provider_id) {
        // Get the email account
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

        try {
            $response = $this->httpClient->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $emailAccount['refresh_token'],
                    'grant_type' => 'refresh_token',
                    'scope' => implode(' ', $this->scopes)
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['access_token'])) {
                // Update the email account with new tokens
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $body['access_token'],
                    $body['refresh_token'] ?? $emailAccount['refresh_token'],
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );

                return [
                    'access_token' => $body['access_token'],
                    'refresh_token' => $body['refresh_token'] ?? $emailAccount['refresh_token']
                ];
            } else {
                throw new Exception('Access token not found in response');
            }

        } catch (RequestException $e) {
            throw new Exception('Failed to refresh access token: ' . $e->getMessage());
        }
    }

    public function listInboxEmails($accessToken) {
        try {
            $response = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ],
                'query' => [
                    // Opcional: parâmetros para paginação ou filtros
                    '$top' => 10, // Obtém os 10 emails mais recentes
                    '$select' => 'subject,from,receivedDateTime' // Campos selecionados
                ]
            ]);

            $emails = json_decode($response->getBody(), true);
            return $emails['value'];

        } catch (RequestException $e) {
            throw new Exception('Erro ao listar emails: ' . $e->getMessage());
        }
    }

    public function authenticateImap($user_id, $provider_id) {
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        $accessToken = $emailAccount['oauth_token'];

        $this->listInboxEmails($accessToken);

      
    }

}
