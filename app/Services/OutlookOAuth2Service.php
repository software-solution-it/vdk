<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Email;
use App\Models\EmailAccount;
use Exception;
use App\Controllers\ErrorLogController; // Importe seu controlador de log de erro

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
    private $errorLogController; // Controlador de log de erro

    public function __construct() {
        $this->httpClient = new Client();
        $this->errorLogController = new ErrorLogController(); // Inicializa o controlador de log de erro
    }

    public function initialize($db) {
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
        $this->errorLogController->logError("Initialized OutlookOAuth2Service with DB.", __FILE__, __LINE__); // Log de inicialização
    }

    public function initializeOAuthParameters($emailAccount, $user_id, $provider_id) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];

        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

        $this->redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);
        $this->errorLogController->logError("OAuth parameters initialized for user_id: $user_id, provider_id: $provider_id", __FILE__, __LINE__); // Log de parâmetros
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $provider_id);
            $this->errorLogController->logError("Generating authorization URL for user ID: $user_id", __FILE__, __LINE__);

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

        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
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
            $this->errorLogController->logError("Requesting access token for user ID: $user_id", __FILE__, __LINE__);

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
            $this->errorLogController->logError('Failed to get access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
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
            $this->errorLogController->logError("Refreshing access token for user ID: $user_id", __FILE__, __LINE__);

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
            $this->errorLogController->logError('Failed to refresh access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
            throw new Exception('Failed to refresh access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
            throw new Exception('Error during token refresh: ' . $e->getMessage());
        }
    }

    public function authenticateImap($user_id, $provider_id) {
        try {
            $this->errorLogController->logError("Entrou no método: $user_id", __FILE__, __LINE__);
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            $this->errorLogController->logError("Recuperou email account: $user_id", __FILE__, __LINE__);
            if (!$emailAccount) {
                $this->errorLogController->logError("Conta não encontrada: $user_id", __FILE__, __LINE__);
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];
            $this->errorLogController->logError("Authenticating IMAP for user ID: $user_id", __FILE__, __LINE__);

            $foldersResponse = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            $folders = json_decode($foldersResponse->getBody(), true);

            foreach ($folders['value'] as $folder) {
                $folderName = $folder['displayName'];

                if (strtolower($folderName) === 'all mail' || strtolower($folderName) === 'todos os emails') {
                    continue;
                }

                $emailsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/mailFolders/{$folder['id']}/messages", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json'
                    ],
                    'query' => [
                        '$top' => 10,
                        '$select' => 'id,subject,from,receivedDateTime,hasAttachments,toRecipients,ccRecipients,isRead,internetMessageId,conversationId'
                    ]
                ]);

                $emails = json_decode($emailsResponse->getBody(), true);

                foreach ($emails['value'] as $emailData) {
                    $messageId = $emailData['id'];
                    $subject = $emailData['subject'] ?? '(Sem Assunto)';
                    $fromAddress = $emailData['from']['emailAddress']['address'] ?? '';
                    $date_received = $emailData['receivedDateTime'] ?? null;
                    $isRead = $emailData['isRead'] ?? false;
                    $toRecipients = implode(', ', array_map(fn($addr) => $addr['emailAddress']['address'], $emailData['toRecipients']));
                    $ccRecipients = implode(', ', array_map(fn($addr) => $addr['emailAddress']['address'], $emailData['ccRecipients'] ?? []));
                    $references = $emailData['conversationId'] ?? '';
                    $inReplyTo = $emailData['internetMessageId'] ?? '';

                    $messageDetailResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/$messageId", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Accept' => 'application/json'
                        ]
                    ]);

                    $messageDetails = json_decode($messageDetailResponse->getBody(), true);
                    $bodyContent = $messageDetails['body']['content'] ?? '';
                    $bodyContentType = $messageDetails['body']['contentType'] ?? '';

                    $emailId = $this->emailModel->saveEmail(
                        $user_id,
                        $messageId,
                        $subject,
                        $fromAddress,
                        $toRecipients,
                        $bodyContent,
                        $date_received,
                        $references,
                        $inReplyTo,
                        $isRead,
                        $folderName, 
                        $ccRecipients,
                        $messageId
                    );

                    if ($emailData['hasAttachments']) {
                        $attachmentsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $accessToken,
                                'Accept' => 'application/json'
                            ]
                        ]);

                        $attachments = json_decode($attachmentsResponse->getBody(), true);

                        foreach ($attachments['value'] as $attachment) {
                            $filename = $attachment['name'] ?? '';
                            $mimeTypeName = $attachment['contentType'] ?? '';
                            $contentBytes = base64_decode($attachment['contentBytes']);

                            if (empty($filename) || $contentBytes === false) {
                                $this->errorLogController->logError("Anexo ignorado: nome do arquivo vazio ou falha na decodificação.", __FILE__, __LINE__, $user_id);
                                continue;
                            }

                            $this->emailModel->saveAttachment(
                                $emailId,
                                $filename,
                                $mimeTypeName,
                                strlen($contentBytes),
                                $contentBytes
                            );
                        }
                    }
                }
            }

            return true;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Error while listing emails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
            throw new Exception('Error while listing emails: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while saving emails and attachments: ' . $e->getMessage(), __FILE__, __LINE__, $user_id); // Log de erro
            throw new Exception('Error while saving emails and attachments: ' . $e->getMessage());
        }
    }
}
