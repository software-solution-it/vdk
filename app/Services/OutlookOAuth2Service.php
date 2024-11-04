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
        'Mail.ReadWrite', // Adicionado para operações de escrita
        'IMAP.AccessAsUser.All',
        'SMTP.Send'
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

        $this->redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        try {
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

            $response = $this->httpClient->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
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
            $this->errorLogController->logError('Failed to refresh access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Failed to refresh access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error during token refresh: ' . $e->getMessage());
        }
    }

    public function authenticateImap($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);

            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];

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
                        '$select' => 'id,subject,from,receivedDateTime,hasAttachments,toRecipients,ccRecipients,isRead,internetMessageId,conversationId,parentFolderId',
                    ]
                ]);

                $emails = json_decode($emailsResponse->getBody(), true);

                foreach ($emails['value'] as $emailData) {
                    $existingEmail = $this->emailModel->getEmailByMessageId($emailData['id'], $user_id);
                    $messageId = $emailData['id'];
                    $subject = $emailData['subject'] ?? '(Sem Assunto)';
                    $fromAddress = $emailData['from']['emailAddress']['address'] ?? '';
                    $date_received = $emailData['receivedDateTime'] ?? null;
                    $isRead = $emailData['isRead'] ?? false;
                    $toRecipients = implode(', ', array_map(fn($addr) => $addr['emailAddress']['address'], $emailData['toRecipients']));
                    $ccRecipients = implode(', ', array_map(fn($addr) => $addr['emailAddress']['address'], $emailData['ccRecipients'] ?? []));
                    $references = $emailData['conversationId'] ?? '';
                    $inReplyTo = $emailData['internetMessageId'] ?? '';
                    $conversationId = $emailData['conversationId'] ?? '';
                    $parentFolderId = $emailData['parentFolderId'] ?? '';

                    // Obter o nome da pasta a partir do parentFolderId
                    $folderName = '';
                    if ($parentFolderId) {
                        $folderResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/mailFolders/$parentFolderId", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $accessToken,
                                'Accept' => 'application/json'
                            ]
                        ]);
                        $folderData = json_decode($folderResponse->getBody(), true);
                        $folderName = $folderData['displayName'] ?? '';
                    }

                    $messageDetailResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/$messageId", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Accept' => 'application/json'
                        ]
                    ]);

                    $messageDetails = json_decode($messageDetailResponse->getBody(), true);
                    $bodyContent = $messageDetails['body']['content'] ?? '';
                    $bodyContentType = $messageDetails['body']['contentType'] ?? '';

                    if ($existingEmail) {
                        // Verificar se o e-mail foi alterado
                        $needsUpdate = false;
                        if ($existingEmail['is_read'] != $isRead) {
                            $needsUpdate = true;
                        }
                        if ($existingEmail['folder_name'] != $folderName) {
                            $needsUpdate = true;
                        }
                        // Adicionar outras comparações conforme necessário
                        if ($needsUpdate) {
                            // Atualizar o e-mail no banco de dados
                            $this->emailModel->updateEmail(
                                $existingEmail['id'],
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
                                $messageId,
                                $conversationId
                            );
                        }
                        continue;
                    } else {
                        // Salvar novo e-mail
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
                            $messageId,
                            $conversationId
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
            }

            return true;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Error while listing emails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while listing emails: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while saving emails and attachments: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while saving emails and attachments: ' . $e->getMessage());
        }
    }

    public function moveEmail($user_id, $provider_id, $messageId, $destinationFolderId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }
    
            $accessToken = $emailAccount['oauth_token'];
    
            $response = $this->httpClient->post("https://graph.microsoft.com/v1.0/me/messages/$messageId/move", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'destinationId' => $destinationFolderId
                ]
            ]);
    
            $movedMessage = json_decode($response->getBody(), true);
    
            $newMessageId = $movedMessage['id'] ?? '';
            if (empty($newMessageId)) {
                throw new Exception('Falha ao obter o novo messageId após mover o e-mail.');
            }
    
            $newFolderId = $movedMessage['parentFolderId'] ?? '';
            $folderResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/mailFolders/$newFolderId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);
            $folderData = json_decode($folderResponse->getBody(), true);
            $folderName = $folderData['displayName'] ?? '';
    

            $this->emailModel->updateEmailAfterMove($messageId, $newMessageId, $folderName);

            $this->emailModel->deleteEmail($messageId);
    
            return true;
    
        } catch (RequestException $e) {
            $this->errorLogController->logError('Error while moving email: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while moving email: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while moving email: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while moving email: ' . $e->getMessage());
        }
    }
    

    public function deleteEmail($user_id, $provider_id, $messageId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];
            $this->errorLogController->logError("Deleting email for user ID: $user_id", __FILE__, __LINE__);

            // Usando a API do Microsoft Graph para deletar o e-mail
            $this->httpClient->delete("https://graph.microsoft.com/v1.0/me/messages/$messageId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);

            // Remover ou marcar o e-mail como deletado no banco de dados
            $this->emailModel->deleteEmail($messageId);

            return true;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Error while deleting email: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while deleting email: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while deleting email: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while deleting email: ' . $e->getMessage());
        }
    }

    public function listFolders($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];

            $foldersResponse = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            $folders = json_decode($foldersResponse->getBody(), true);

            // Opcionalmente, atualizar as pastas no banco de dados

            return $folders['value'];

        } catch (RequestException $e) {
            $this->errorLogController->logError('Error while listing folders: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while listing folders: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while listing folders: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while listing folders: ' . $e->getMessage());
        }
    }

    public function listEmailsByConversation($user_id, $provider_id, $conversation_id)
    {
        try {
           
            $emails = $this->emailModel->getEmailsByConversationId($user_id, $conversation_id);
    
            usort($emails, function($a, $b) {
                return strtotime($a['date_received']) - strtotime($b['date_received']);
            });
    
            return $emails;
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Error while listing emails by conversation: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while listing emails by conversation: ' . $e->getMessage());
        }
    }
    
}
