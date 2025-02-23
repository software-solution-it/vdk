<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Config\Database;
use App\Models\EmailFolder;

use App\Models\FolderAssociation;

use Exception;
use App\Controllers\ErrorLogController;

class OutlookOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $httpClient;
    private $clientId;

    private $emailFolderModel;

    private $folderAssociationModel;
    private $webhookService;

    private $clientSecret;
    private $redirectUri;
    private $scopes = [
        'offline_access',
        'User.Read',
        'Mail.Read',
        'Mail.Send',
        'Mail.ReadWrite',
        'IMAP.AccessAsUser.All',
        'SMTP.Send'
    ];
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->httpClient = new Client();
        $this->errorLogController = new ErrorLogController();
        $this->webhookService = new WebhookService();
        $this->emailModel = new Email($db);
        $this->emailFolderModel = new EmailFolder($db); 
        $this->folderAssociationModel = new FolderAssociation($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthParameters($emailAccount, $user_id, $email_id) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];
    
        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'email_id' => $email_id]));
    
        // Certifique-se de que o redirecionamento é correto
        $this->redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);
    }
    
    public function getAuthorizationUrl($user_id, $email_id) {
        try {
            // Obter a conta de e-mail
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $email_id");
            }
    
            // Inicializar parâmetros OAuth
            $this->initializeOAuthParameters($emailAccount, $user_id, $email_id);
    
            // Criar um state seguro contendo informações úteis
            $state = base64_encode(json_encode([
                'user_id' => $user_id,
                'email_id' => $email_id,
                'timestamp' => time()
            ]));
    
            // Defina corretamente a URL de autorização com o tenant correto (se necessário)
            $authorizationUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'
                . http_build_query([
                    'client_id' => $this->clientId,
                    'response_type' => 'code',
                    'redirect_uri' => $this->redirectUri,
                    'scope' => implode(' ', $this->scopes), // Certifique-se de que o escopo está correto
                    'response_mode' => 'query',
                    'state' => $state
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
    
    public function getAccessToken($user_id, $email_id, $code) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and email ID: $email_id");
            }
    
            if ($emailAccount['refresh_token']) {
                return $this->refreshAccessToken($user_id, $email_id);
            }
    
            $this->initializeOAuthParameters($emailAccount, $user_id, $email_id);
    
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
            $this->errorLogController->logError('Failed to get access token: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error during access token retrieval: ' . $e->getMessage());
        }
    }
    
    

    public function refreshAccessToken($user_id, $email_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and email ID: $email_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $email_id);

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

    public function syncEmailsOutlook($user_id, $email_account_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_account_id);
    
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and email ID: $email_account_id");
            }
    
            $accessToken = $emailAccount['oauth_token'];
    
            $associationsResponse = $this->folderAssociationModel->getAssociationsByEmailAccount($email_account_id);
    
            if ($associationsResponse['Status'] === 'Success') {
                $associations = $associationsResponse['Data'];
            } else {
                $associations = [];
            }

            $folders = ['TRASH_PROCESSED', 'INBOX_PROCESSED', 'SPAM_PROCESSED'];

            $existingFolders = $this->getMailFolders($accessToken);
        
            foreach ($folders as $folderName) {
                if (in_array($folderName, array_column($existingFolders, 'displayName'))) {
                    continue; 
                }
         
                $url = 'https://graph.microsoft.com/v1.0/me/mailFolders';
                $body = [
                    "displayName" => $folderName
                ];
        
                try {
                     $this->httpClient->post($url, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $body
                    ]);

        
                } catch (Exception $e) {
                    $this->errorLogController->logError("Erro ao criar a pasta '$folderName': " . $e->getMessage(), __FILE__, __LINE__);
                }
            }
    
            foreach (['INBOX', 'SPAM', 'TRASH'] as $folderType) {
                $filteredAssociations = array_filter($associations, function ($assoc) use ($folderType) {
                    return $assoc['folder_type'] === $folderType;
                });
    
                if (!empty($filteredAssociations)) {
                    $association = current($filteredAssociations);
    
                    $originalFolderName = $association['folder_name'];
                    $associatedFolderName = $association['associated_folder_name'];
    
                    $originalFolderId = $this->getFolderIdByName($originalFolderName, $accessToken);
                    if (!$originalFolderId) {
                        continue;
                    }
    
                    $associatedFolderId = $this->getFolderIdByName($associatedFolderName, $accessToken);
                    if (!$associatedFolderId) {
                        $associatedFolderId = $this->createFolder($associatedFolderName, $accessToken);
                        if (!$associatedFolderId) {
                            continue;
                        }
                    }
    
                    $emailsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/mailFolders/{$originalFolderId}/messages", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Accept' => 'application/json'
                        ],
                        'query' => [
                            '$select' => 'id',
                            '$top' => 1000
                        ]
                    ]);
    
                    $emails = json_decode($emailsResponse->getBody(), true);
    
                    if (!isset($emails['value']) || !is_array($emails['value'])) {
                        continue;
                    }
    
                    foreach ($emails['value'] as $emailData) {
                        $messageId = $emailData['id'];
    
                        try {
                            $this->moveEmail($messageId, $associatedFolderId, $accessToken, $user_id);
                            $this->emailModel->deleteEmailByMessageId($messageId, $user_id);
    
                        } catch (Exception $e) {
                            $this->errorLogController->logError(
                                "Erro ao mover e-mail {$messageId} para a pasta associada $associatedFolderName: " . $e->getMessage(),
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
                        }
                    }
                }
            }
    
            $foldersResponse = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);
    
            $folders = json_decode($foldersResponse->getBody(), true);
    
            if (!isset($folders['value']) || !is_array($folders['value'])) {
                throw new Exception("Falha ao recuperar pastas de e-mail");
            }
    
            $folderNames = array_map(function ($folder) {
                return $folder['displayName'];
            }, $folders['value']);
    
            $syncedFolders = $this->emailFolderModel->syncFolders($email_account_id, $folderNames);
    
            foreach ($folders['value'] as $folder) {
                $folderName = $folder['displayName'];
                $folderId = $folder['id'];
    
                if (!isset($syncedFolders[$folderName])) {
                    continue;
                }
    
                $folderDbId = $syncedFolders[$folderName];
    
                $emailsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/mailFolders/{$folderId}/messages", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json'
                    ],
                    'query' => [
                        '$select' => 'id,subject,from,receivedDateTime,hasAttachments,toRecipients,ccRecipients,isRead,internetMessageId,conversationId,bodyPreview,conversationIndex',
                        '$top' => 1000
                    ]
                ]);
    
                $emails = json_decode($emailsResponse->getBody(), true);
    
                if (!isset($emails['value']) || !is_array($emails['value'])) {
                    continue;
                }
    
                $storedMessageIds = $this->emailModel->getEmailIdsByFolderId($user_id, $folderDbId);
                $processedMessageIds = [];
    
                foreach ($emails['value'] as $emailData) {
                    try {
                        $messageId = $emailData['id'];
                        $processedMessageIds[] = $messageId;
    
                        $fromAddress = $emailData['from']['emailAddress']['address'] ?? '';
                        $fromName = $emailData['from']['emailAddress']['name'] ?? '';
                        $subject = $emailData['subject'] ?? '(Sem Assunto)';
                        $date_received = $emailData['receivedDateTime'] ?? null;
                        if ($date_received) {
                            $date = new \DateTime($date_received, new \DateTimeZone('UTC'));  // Assume que a data está em UTC
                            $date->setTimezone(new \DateTimeZone('America/Sao_Paulo'));  // Ajusta para o timezone de São Paulo
                            $date_received = $date->format('Y-m-d H:i:s');  // Formata para o formato desejado
                        } 
                        $isRead = isset($emailData['isRead']) ? (int) $emailData['isRead'] : 0;
                        $toRecipients = implode(', ', array_map(function($recipient) {
                            return $recipient['emailAddress']['address'];
                        }, $emailData['toRecipients'] ?? []));
    
                        $ccRecipients = implode(', ', array_map(function($recipient) {
                            return $recipient['emailAddress']['address'];
                        }, $emailData['ccRecipients'] ?? []));
    
                        $inReplyTo = $emailData['internetMessageId'] ?? '';
                        $conversationId = $emailData['conversationId'] ?? '';
                        $bodyPreview = $emailData['bodyPreview'] ?? '';
                        $body_text = $bodyPreview;
    
                        $messageDetailResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/{$messageId}", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $accessToken,
                                'Accept' => 'application/json'
                            ],
                            'query' => [
                                '$select' => 'body,uniqueBody,conversationIndex,internetMessageHeaders'
                            ]
                        ]);
    
                        $messageDetails = json_decode($messageDetailResponse->getBody(), true);
                        $bodyContent = $messageDetails['body']['content'] ?? '';
                        $bodyContentType = $messageDetails['body']['contentType'] ?? '';
    
                        if ($bodyContentType == 'html') {
                            $body_text = strip_tags($bodyContent);
                        } else {
                            $body_text = $bodyContent;
                        }
    
                        $references = '';
                        $conversation_step = 1;
                        $headers = $messageDetails['internetMessageHeaders'] ?? [];
                        foreach ($headers as $header) {
                            if (strcasecmp($header['name'], 'References') == 0) {
                                $references = $header['value'];
                                break;
                            }
                        }
                        if ($references) {
                            $referenceCount = count(explode(' ', trim($references)));
                            $conversation_step = $referenceCount + 1;
                        }
    
                        $existingEmail = $this->emailModel->getEmailByMessageId($messageId, $user_id);
    
                        if ($existingEmail) {
                            $needsUpdate = false;
                            if ($existingEmail['is_read'] != $isRead) {
                                $needsUpdate = true;
                            }
                            if ($existingEmail['folder_id'] != $folderDbId) {
                                $needsUpdate = true;
                            }
                            if ($needsUpdate) {
                                $this->emailModel->deleteEmail($messageId);
                            }
                            continue;
                        } else {
                            $emailId = $this->emailModel->saveEmail(
                                $user_id,
                                $email_account_id,
                                $messageId,
                                $subject,
                                $fromAddress,
                                $toRecipients,
                                $bodyContent,
                                $body_text,
                                $date_received,
                                $references,
                                $inReplyTo,
                                $isRead,
                                $folderDbId,
                                $ccRecipients,
                                $conversationId,
                                $conversationId,
                                $conversation_step,
                                $fromName
                            );
    
                            if ($emailData['hasAttachments']) {
                                try {
                                    $attachmentsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/{$emailData['id']}/attachments", [
                                        'headers' => [
                                            'Authorization' => 'Bearer ' . $accessToken,
                                            'Accept' => 'application/json'
                                        ]
                                    ]);
                            
                                    $attachments = json_decode($attachmentsResponse->getBody(), true);
                            
                                    if (isset($attachments['value']) && is_array($attachments['value'])) { 
                                        foreach ($attachments['value'] as $attachment) {
                                            try {
                                                $filename = $attachment['name'] ?? null;
                                                $contentBytes = isset($attachment['contentBytes']) ? base64_decode($attachment['contentBytes']) : null;
                            
                                                if (is_null($filename) || empty($filename)) {
                                                    error_log("Anexo ignorado: o nome do arquivo está nulo.");
                                                    continue;
                                                }
                            
                                                if ($contentBytes === false) {
                                                    error_log("Falha ao obter o conteúdo do anexo: $filename");
                                                    continue;
                                                }
                            
                                                $mimeTypeName = $attachment['contentType'] ?? 'application/octet-stream';
                            
                                                // Salvar o anexo no banco de dados
                                                $this->emailModel->saveAttachment(
                                                    $emailId,
                                                    $filename,
                                                    $mimeTypeName,
                                                    strlen($contentBytes),
                                                    $contentBytes
                                                );
                            
                                                // Substituir "cid:" diretamente no HTML do corpo da mensagem
                                                if (isset($body_html)) {
                                                    $body_html = preg_replace(
                                                        '/<img[^>]+src="cid:([^"]+)"/',
                                                        '<img src="data:' . $mimeTypeName . ';base64,' . base64_encode($contentBytes) . '"',
                                                        $body_html
                                                    );
                                                }
                                            } catch (Exception $e) {
                                                $this->errorLogController->logError("Erro ao salvar e substituir anexo: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    $this->errorLogController->logError("Erro ao processar anexos para a mensagem {$emailData['id']}: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                                }
                            }
                            
    
                            // Processar anexos
                            if ($emailData['hasAttachments']) {
                                $attachmentsResponse = $this->httpClient->get("https://graph.microsoft.com/v1.0/me/messages/{$messageId}/attachments", [
                                    'headers' => [
                                        'Authorization' => 'Bearer ' . $accessToken,
                                        'Accept' => 'application/json'
                                    ]
                                ]);
    
                                $attachments = json_decode($attachmentsResponse->getBody(), true);
    
                                foreach ($attachments['value'] as $attachment) {
                                    try {
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
                                    } catch (Exception $e) {
                                        $this->errorLogController->logError("Erro ao salvar anexo: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                                    }
                                }
                            }
    
                            $event = [
                                'Status' => 'Success',
                                'Message' => 'Email saved successfully',
                                'Data' => [
                                    'email_account_id' => $email_account_id,
                                    'email_id' => $emailId,
                                    'subject' => $subject,
                                    'from' => $fromAddress,
                                    'to' => $toRecipients,
                                    'received_at' => $date_received,
                                    'user_id' => $user_id,
                                    'folder' => $folderName,
                                    'uuid' => uniqid(),
                                ]
                            ];
                            $this->webhookService->triggerEvent($event, $user_id);
                        }
                    } catch (Exception $e) {
                        $this->errorLogController->logError("Erro ao processar e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                    }
                }
    
                $deletedMessageIds = array_diff($storedMessageIds, $processedMessageIds);
                foreach ($deletedMessageIds as $deletedMessageId) {
                    $this->emailModel->deleteEmailByMessageId($deletedMessageId, $user_id);
                }
    
            }
    
            return true;
    
        } catch (RequestException $e) {
            $event = [
                'Status' => 'Failed',
                'Message' => 'Failed to sync emails',
                'Data' => [
                    'email_account_id' => $email_account_id,
                    'user_id' => $user_id,
                    'uuid' => uniqid(),
                ]
            ];
            $this->webhookService->triggerEvent($event, $user_id);
    
        } catch (Exception $e) {
            $event = [
                'Status' => 'Failed',
                'Message' => 'Failed to sync emails',
                'Data' => [
                    'email_account_id' => $email_account_id,
                    'user_id' => $user_id,
                    'uuid' => uniqid(),
                ]
            ];
            $this->webhookService->triggerEvent($event, $user_id);
        }
    }

    private function getMailFolders($accessToken)
{
    try {
        $response = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json'
            ]
        ]);

        $folders = json_decode($response->getBody(), true);
        return $folders['value'] ?? [];
    } catch (Exception $e) {
        $this->errorLogController->logError("Erro ao obter pastas de e-mail: " . $e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}
    
    // Função auxiliar para obter o ID da pasta pelo nome
    private function getFolderIdByName($folderName, $accessToken) {
        $response = $this->httpClient->get('https://graph.microsoft.com/v1.0/me/mailFolders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json'
            ],
            'query' => [
                '$filter' => "displayName eq '{$folderName}'"
            ]
        ]);
    
        $folders = json_decode($response->getBody(), true);
        if (isset($folders['value'][0]['id'])) {
            return $folders['value'][0]['id'];
        }
        return null;
    }
    
    // Função auxiliar para criar uma pasta
    private function createFolder($folderName, $accessToken) {
        $response = $this->httpClient->post('https://graph.microsoft.com/v1.0/me/mailFolders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'displayName' => $folderName
            ]
        ]);
    
        $folder = json_decode($response->getBody(), true);
        if (isset($folder['id'])) {
            return $folder['id'];
        }
        return null;
    }
    
    // Função para mover um e-mail para uma pasta
    private function moveEmail($messageId, $destinationFolderId, $accessToken, $user_id) {
        $response = $this->httpClient->post("https://graph.microsoft.com/v1.0/me/messages/{$messageId}/move", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'destinationId' => $destinationFolderId,
            ]
        ]);
    
        if ($response->getStatusCode() != 200) {
            throw new Exception("Falha ao mover o e-mail {$messageId} para a pasta {$destinationFolderId}");
        }
    }
        

    public function deleteEmail($user_id, $email_id, $messageId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and email ID: $email_id");
            }

            $accessToken = $emailAccount['oauth_token'];

            $this->httpClient->delete("https://graph.microsoft.com/v1.0/me/messages/$messageId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);

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

    public function listFolders($user_id, $email_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $email_id");
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

    public function listEmailsByConversation($user_id, $conversation_id)
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
