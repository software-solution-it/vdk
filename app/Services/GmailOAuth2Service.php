<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Config\Database;
use Exception;
use App\Controllers\ErrorLogController;

class GmailOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $httpClient;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.modify'
    ];
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->httpClient = new Client();
        $this->errorLogController = new ErrorLogController();
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthParameters($emailAccount, $user_id, $provider_id) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];

        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

        $this->redirectUri = "http://localhost:3000/callback";
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

            $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

            $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $this->clientId,
                'response_type' => 'code',
                'redirect_uri' => $this->redirectUri,
                'scope' => implode(' ', $this->scopes),
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $extraParams // Use `state` para passar informações extras
            ]);

            return [
                'status' => true,
                'authorization_url' => $authorizationUrl
            ];

        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error generating authorization URL: ' . $e->getMessage());
        }
    }

    public function getAccessToken($user_id, $provider_id, $code) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

            $response = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code'
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['access_token']) && isset($body['refresh_token'])) {
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
            $this->errorLogController->logError('Failed to get access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error during access token retrieval: ' . $e->getMessage());
        }
    }

    public function refreshAccessToken($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);

            $response = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $emailAccount['refresh_token'],
                    'grant_type' => 'refresh_token'
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['access_token'])) {
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
            $this->errorLogController->logError('Failed to refresh access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Failed to refresh access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error during token refresh: ' . $e->getMessage());
        }
    }
}
