<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Config\Database;
use App\Models\FolderAssociation;

use Exception;
use App\Controllers\ErrorLogController;

class OutlookOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $httpClient;
    private $clientId;

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
        $this->folderAssociationModel = new FolderAssociation($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthParameters($emailAccount, $user_id, $email_id) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];

        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'email_id' => $email_id]));

        $this->redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);
    }

    public function getAuthorizationUrl($user_id, $email_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and provider ID: $email_id");
            }

            $this->initializeOAuthParameters($emailAccount, $user_id, $email_id);
      

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

    public function getAccessToken($user_id, $email_id, $code) {
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

    public function syncEmailsOutlook($user_id, $email_account_id, $email_id) {
        try {
            // Passo 1: Obter a conta de e-mail e o token de acesso
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
    
            if (!$emailAccount) {
                throw new Exception("Email account not found for user ID: $user_id and email ID: $email_id");
            }
    
            $accessToken = $emailAccount['oauth_token'];
    
            // Passo 2: Recuperar as associações de pastas de e-mail do banco de dados
            $associationsResponse = $this->folderAssociationModel->getAssociationsByEmailAccount($email_account_id);
    
            if ($associationsResponse['Status'] === 'Success') {
                $associations = $associationsResponse['Data'];
            } else {
                $this->errorLogController->logError(
                    "Falha ao recuperar associações de pastas",
                    __FILE__,
                    __LINE__,
                    $user_id
                );
                $associations = []; // Define como um array vazio para evitar erros posteriores
            }
    
            // Passo 3: Processar as associações de pastas
            foreach (['INBOX', 'SPAM', 'TRASH'] as $folderType) {
                // Filtrar associações pelo tipo de pasta
                $filteredAssociations = array_filter($associations, function ($assoc) use ($folderType) {
                    return $assoc['folder_type'] === $folderType;
                });
    
                if (!empty($filteredAssociations)) {
                    $association = current($filteredAssociations);
    
                    $originalFolderName = $association['folder_name'];
                    $associatedFolderName = $association['associated_folder_name'];
    
                    // Obter o ID da pasta original
                    $originalFolderId = $this->getFolderIdByName($originalFolderName, $accessToken);
                    if (!$originalFolderId) {
                        $this->errorLogController->logError(
                            "Pasta original '$originalFolderName' não encontrada.",
                            __FILE__,
                            __LINE__,
                            $user_id
                        );
                        continue;
                    }
    
                    // Obter ou criar o ID da pasta associada
                    $associatedFolderId = $this->getFolderIdByName($associatedFolderName, $accessToken);
                    if (!$associatedFolderId) {
                        $associatedFolderId = $this->createFolder($associatedFolderName, $accessToken);
                        if (!$associatedFolderId) {
                            $this->errorLogController->logError(
                                "Falha ao criar a pasta associada '$associatedFolderName'.",
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
                            continue;
                        }
                    }
    
                    // Obter mensagens da pasta original
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
                        $this->errorLogController->logError(
                            "Nenhum e-mail encontrado na pasta '$originalFolderName'.",
                            __FILE__,
                            __LINE__,
                            $user_id
                        );
                        continue;
                    }
    
                    foreach ($emails['value'] as $emailData) {
                        $messageId = $emailData['id'];
    
                        try {
                            // Mover o e-mail para a pasta associada
                            $this->moveEmail($messageId, $associatedFolderId, $accessToken, $user_id);
                            // Deletar o e-mail do banco de dados
                            $this->emailModel->deleteEmailByMessageId($messageId, $user_id);
    
                            $this->errorLogController->logError(
                                "E-mail {$messageId} movido da pasta '$originalFolderName' para '$associatedFolderName'.",
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
                        } catch (Exception $e) {
                            $this->errorLogController->logError(
                                "Erro ao mover e-mail {$messageId} para a pasta associada $associatedFolderName: " . $e->getMessage(),
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
                        }
                    }
                } else {
                    $this->errorLogController->logError(
                        "Nenhuma associação encontrada para a pasta $folderType",
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
                }
            }
    
            // Passo 4: Obter a lista de pastas de e-mail via Microsoft Graph API
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
    
            // Extrair nomes das pastas
            $folderNames = array_map(function ($folder) {
                return $folder['displayName'];
            }, $folders['value']);
    
            // Sincronizar pastas com o banco de dados
            $syncedFolders = $this->emailFolderModel->syncFolders($email_account_id, $folderNames);
    
            // Passo 5: Processar cada pasta
            foreach ($folders['value'] as $folder) {
                $folderName = $folder['displayName'];
                $folderId = $folder['id'];
    
                if (!isset($syncedFolders[$folderName])) {
                    // Pasta não sincronizada no banco de dados, ignorar
                    $this->errorLogController->logError(
                        "Pasta '$folderName' não está sincronizada no banco de dados. Ignorando...",
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
                    continue;
                }
    
                $folderDbId = $syncedFolders[$folderName];
    
                // Obter mensagens da pasta
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
                    $this->errorLogController->logError(
                        "Nenhum e-mail encontrado na pasta '$folderName'.",
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
                    continue;
                }
    
                // Obter IDs de mensagens armazenadas no banco de dados
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
                        $isRead = $emailData['isRead'] ?? false;
                        $toRecipients = implode(', ', array_map(function($recipient) {
                            return $recipient['emailAddress']['address'];
                        }, $emailData['toRecipients'] ?? []));
    
                        $ccRecipients = implode(', ', array_map(function($recipient) {
                            return $recipient['emailAddress']['address'];
                        }, $emailData['ccRecipients'] ?? []));
    
                        $inReplyTo = $emailData['internetMessageId'] ?? '';
                        $conversationId = $emailData['conversationId'] ?? '';
                        $bodyPreview = $emailData['bodyPreview'] ?? '';
                        $body_text = $bodyPreview; // Assumindo que bodyPreview é o texto sem HTML
    
                        // Obter o conteúdo completo da mensagem
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
                            // Remove tags HTML para obter o body_text
                            $body_text = strip_tags($bodyContent);
                        } else {
                            $body_text = $bodyContent;
                        }
    
                        // Calcular conversation_step
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
    
                        // Verificar se o e-mail já existe
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
                                $this->emailModel->updateEmail(
                                    $existingEmail['id'],
                                    $user_id,
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
                                    $conversation_step,
                                    $fromName
                                );
                            }
                            continue;
                        } else {
                            // Salvar e-mail no banco de dados
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
                                $conversation_step,
                                $fromName
                            );
    
                            // Processar imagens embutidas no conteúdo
                            if ($bodyContentType == 'html' && $bodyContent) {
                                preg_match_all('/<img[^>]+src="data:image\/([^;]+);base64,([^"]+)"/', $bodyContent, $matches, PREG_SET_ORDER);
    
                                foreach ($matches as $match) {
                                    try {
                                        $imageType = $match[1];
                                        $base64Data = $match[2];
                                        $decodedContent = base64_decode($base64Data);
    
                                        if ($decodedContent !== false) {
                                            $filename = uniqid("inline_img_") . '.' . $imageType;
                                            $fullMimeType = 'image/' . $imageType;
    
                                            $this->emailModel->saveAttachment(
                                                $emailId,
                                                $filename,
                                                $fullMimeType,
                                                strlen($decodedContent),
                                                $decodedContent
                                            );
                                        }
                                    } catch (Exception $e) {
                                        $this->errorLogController->logError("Erro ao processar imagem embutida: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                                    }
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
    
                            // Disparar webhook
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
    
                // Remover e-mails do banco de dados que não estão mais no servidor
                $deletedMessageIds = array_diff($storedMessageIds, $processedMessageIds);
                foreach ($deletedMessageIds as $deletedMessageId) {
                    $this->emailModel->deleteEmailByMessageId($deletedMessageId, $user_id);
                    $this->errorLogController->logError(
                        "E-mail com Message-ID $deletedMessageId foi deletado no servidor e removido do banco de dados.",
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
                }
    
                $this->errorLogController->logError(
                    "Sincronização de e-mails concluída para a pasta '$folderName'.",
                    __FILE__,
                    __LINE__,
                    $user_id
                );
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
    
            $this->errorLogController->logError('Error while syncing emails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while syncing emails: ' . $e->getMessage());
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
    
            $this->errorLogController->logError('Error while syncing emails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Error while syncing emails: ' . $e->getMessage());
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
