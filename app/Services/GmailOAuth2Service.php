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

    private function initializeOAuthParameters($emailAccount) {
        $this->clientId = $emailAccount['client_id'];
        $this->clientSecret = $emailAccount['client_secret'];
        $this->redirectUri = "http://localhost:3000/callback";
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount);

            $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

            $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $this->clientId,
                'response_type' => 'code',
                'redirect_uri' => $this->redirectUri,
                'scope' => implode(' ', $this->scopes),
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $extraParams
            ]);

            return [
                'status' => true,
                'authorization_url' => $authorizationUrl
            ];

        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao gerar URL de autorização: ' . $e->getMessage());
        }
    }

    public function getAccessToken($user_id, $provider_id, $code) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount);

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
                throw new Exception('Token de acesso ou refresh token não encontrado na resposta');
            }

        } catch (RequestException $e) {
            $this->errorLogController->logError('Falha ao obter token de acesso: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Falha ao obter token de acesso: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao recuperar token de acesso: ' . $e->getMessage());
        }
    }

    public function refreshAccessToken($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $this->initializeOAuthParameters($emailAccount);

            $response = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $emailAccount['client_id'],
                    'client_secret' => $emailAccount['client_secret'],
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
                throw new Exception('Token de acesso não encontrado na resposta');
            }

        } catch (RequestException $e) {
            $this->errorLogController->logError('Falha ao atualizar token de acesso: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Falha ao atualizar token de acesso: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao atualizar token: ' . $e->getMessage());
        }
    }

    public function listFolders($user_id, $provider_id) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];

            $response = $this->httpClient->get('https://gmail.googleapis.com/gmail/v1/users/me/labels', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            $body = json_decode($response->getBody(), true);


            return $body['labels'];

        } catch (RequestException $e) {
            $this->errorLogController->logError('Erro ao listar pastas: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar pastas: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao listar pastas: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar pastas: ' . $e->getMessage());
        }
    }

    public function listEmails($user_id, $provider_id, $labelIds = []) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];

            $query = [];
            if (!empty($labelIds)) {
                $query['labelIds'] = implode(',', $labelIds);
            }

            $response = $this->httpClient->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ],
                'query' => $query
            ]);

            $body = json_decode($response->getBody(), true);

            $messages = $body['messages'] ?? [];

            foreach ($messages as $message) {
                $messageId = $message['id'];

                $messageResponse = $this->httpClient->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json'
                    ],
                    'query' => [
                        'format' => 'full'
                    ]
                ]);

                $messageBody = json_decode($messageResponse->getBody(), true);
                $emailData = $this->parseGmailMessage($messageBody);

                $existingEmail = $this->emailModel->getEmailByMessageId($messageId, $user_id);
                if ($existingEmail) {
                    $needsUpdate = false;
                    if ($existingEmail['is_read'] != $emailData['isRead']) {
                        $needsUpdate = true;
                    }
                    if ($existingEmail['folder_name'] != $emailData['folderName']) {
                        $needsUpdate = true;
                    }
                    if ($needsUpdate) {
                        $this->emailModel->updateEmail(
                            $existingEmail['id'],
                            $user_id,
                            $messageId,
                            $emailData['subject'],
                            $emailData['fromAddress'],
                            $emailData['toRecipients'],
                            $emailData['bodyContent'],
                            $emailData['date_received'],
                            $emailData['references'],
                            $emailData['inReplyTo'],
                            $emailData['isRead'],
                            $emailData['folderName'],
                            $emailData['ccRecipients'],
                            $messageId,
                            $emailData['conversationId']
                        );
                    }
                } else {
                    $emailId = $this->emailModel->saveEmail(
                        $user_id,
                        $messageId,
                        $emailData['subject'],
                        $emailData['fromAddress'],
                        $emailData['toRecipients'],
                        $emailData['bodyContent'],
                        $emailData['date_received'],
                        $emailData['references'],
                        $emailData['inReplyTo'],
                        $emailData['isRead'],
                        $emailData['folderName'],
                        $emailData['ccRecipients'],
                        $messageId,
                        $emailData['conversationId']
                    );

                    if (!empty($messageBody['payload']['parts'])) {
                        foreach ($messageBody['payload']['parts'] as $part) {
                            if (isset($part['filename']) && !empty($part['filename']) && isset($part['body']['attachmentId'])) {
                                $attachmentId = $part['body']['attachmentId'];
                                $attachment = $this->getGmailAttachment($accessToken, $messageId, $attachmentId);

                                if ($attachment && isset($attachment['data'])) {
                                    $contentBytes = base64_decode(strtr($attachment['data'], '-_', '+/'));
                                    if ($contentBytes !== false) {
                                        $this->emailModel->saveAttachment(
                                            $emailId,
                                            $part['filename'],
                                            $part['mimeType'],
                                            strlen($contentBytes),
                                            $contentBytes
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return true;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Erro ao listar e-mails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar e-mails: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao listar e-mails: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar e-mails: ' . $e->getMessage());
        }
    }

    private function parseGmailMessage($message) {
        $payload = $message['payload'];
        $headers = $payload['headers'];

        $emailData = [
            'id' => $message['id'],
            'threadId' => $message['threadId'],
            'subject' => '',
            'fromAddress' => '',
            'toRecipients' => '',
            'ccRecipients' => '',
            'date_received' => '',
            'isRead' => false,
            'bodyContent' => '',
            'conversationId' => $message['threadId'],
            'references' => '',
            'inReplyTo' => '',
            'folderName' => '',
        ];

        foreach ($headers as $header) {
            switch (strtolower($header['name'])) {
                case 'subject':
                    $emailData['subject'] = $header['value'];
                    break;
                case 'from':
                    $emailData['fromAddress'] = $header['value'];
                    break;
                case 'to':
                    $emailData['toRecipients'] = $header['value'];
                    break;
                case 'cc':
                    $emailData['ccRecipients'] = $header['value'] ?? '';
                    break;
                case 'date':
                    $emailData['date_received'] = $header['value'];
                    break;
                case 'references':
                    $emailData['references'] = $header['value'] ?? '';
                    break;
                case 'in-reply-to':
                    $emailData['inReplyTo'] = $header['value'] ?? '';
                    break;
            }
        }

        $emailData['isRead'] = !in_array('UNREAD', $message['labelIds']);

        $emailData['bodyContent'] = $this->getEmailBody($payload);

        $emailData['folderName'] = implode(', ', $message['labelIds']);

        return $emailData;
    }

    private function getEmailBody($payload) {
        $body = '';
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if ($part['mimeType'] === 'text/html') {
                    $body .= base64_decode(strtr($part['body']['data'], '-_', '+/'));
                } elseif ($part['mimeType'] === 'text/plain' && empty($body)) {
                    $body .= base64_decode(strtr($part['body']['data'], '-_', '+/'));
                }
            }
        } else {
            if (isset($payload['body']['data'])) {
                $body = base64_decode(strtr($payload['body']['data'], '-_', '+/'));
            }
        }
        return $body;
    }
    

    private function getGmailAttachment($accessToken, $messageId, $attachmentId) {
        try {
            $response = $this->httpClient->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId/attachments/$attachmentId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            return null;
        }
    }

    public function moveEmail($user_id, $provider_id, $messageId, $destinationLabelId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }
    
            $accessToken = $emailAccount['oauth_token'];
    
            // Obtenha o estado atual do e-mail
            $messageResponse = $this->httpClient->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ],
                'query' => [
                    'format' => 'minimal'
                ]
            ]);
    
            $message = json_decode($messageResponse->getBody(), true);
            $currentLabels = $message['labelIds'] ?? [];
    
            // Mova o e-mail no Gmail
            $this->httpClient->post("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId/modify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'addLabelIds' => [$destinationLabelId],
                    'removeLabelIds' => $currentLabels
                ] 
            ]);
    
            $this->emailModel->updateLabel($messageId, $destinationLabelId);
    
            return true;
    
        } catch (RequestException $e) {
            $this->errorLogController->logError('Erro ao mover e-mail: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao mover e-mail: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao mover e-mail: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao mover e-mail: ' . $e->getMessage());
        }
    }
    

    public function deleteEmail($user_id, $provider_id, $messageId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];

            $this->httpClient->delete("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);

            $this->emailModel->deleteEmail($messageId);

            return true;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Erro ao deletar e-mail: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao deletar e-mail: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao deletar e-mail: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao deletar e-mail: ' . $e->getMessage());
        }
    }

    public function listEmailsByConversation($user_id, $provider_id, $conversationId) {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para o usuário ID: $user_id e provider ID: $provider_id");
            }

            $accessToken = $emailAccount['oauth_token'];
            $response = $this->httpClient->get("https://gmail.googleapis.com/gmail/v1/users/me/conversations/$conversationId", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            $messages = $body['messages'] ?? [];

            $emails = [];
            foreach ($messages as $message) {
                $messageId = $message['id'];

                $messageResponse = $this->httpClient->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/$messageId", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json'
                    ],
                    'query' => [
                        'format' => 'full'
                    ]
                ]);
 
                $messageBody = json_decode($messageResponse->getBody(), true);
                $emailData = $this->parseGmailMessage($messageBody);

                $emails[] = $emailData;

                $existingEmail = $this->emailModel->getEmailByMessageId($messageId, $user_id);
                if ($existingEmail) {
                    $needsUpdate = false;
                    if ($existingEmail['is_read'] != $emailData['isRead']) {
                        $needsUpdate = true;
                    }
                    if ($existingEmail['folder_name'] != $emailData['folderName']) {
                        $needsUpdate = true;
                    }
                    if ($needsUpdate) {
                        $this->emailModel->updateEmail(
                            $existingEmail['id'],
                            $user_id,
                            $messageId,
                            $emailData['subject'],
                            $emailData['fromAddress'],
                            $emailData['toRecipients'],
                            $emailData['bodyContent'],
                            $emailData['date_received'],
                            $emailData['references'],
                            $emailData['inReplyTo'],
                            $emailData['isRead'],
                            $emailData['folderName'],
                            $emailData['ccRecipients'],
                            $messageId,
                            $emailData['conversationId']
                        );
                    }
                } else {
                    $emailId = $this->emailModel->saveEmail(
                        $user_id,
                        $messageId,
                        $emailData['subject'],
                        $emailData['fromAddress'],
                        $emailData['toRecipients'],
                        $emailData['bodyContent'],
                        $emailData['date_received'],
                        $emailData['references'],
                        $emailData['inReplyTo'],
                        $emailData['isRead'],
                        $emailData['folderName'],
                        $emailData['ccRecipients'],
                        $messageId,
                        $emailData['conversationId']
                    );

                    if (!empty($messageBody['payload']['parts'])) {
                        foreach ($messageBody['payload']['parts'] as $part) {
                            if (isset($part['filename']) && !empty($part['filename']) && isset($part['body']['attachmentId'])) {
                                $attachmentId = $part['body']['attachmentId'];
                                $attachment = $this->getGmailAttachment($accessToken, $messageId, $attachmentId);

                                if ($attachment && isset($attachment['data'])) {
                                    $contentBytes = base64_decode(strtr($attachment['data'], '-_', '+/'));
                                    if ($contentBytes !== false) {
                                        $this->emailModel->saveAttachment(
                                            $emailId,
                                            $part['filename'],
                                            $part['mimeType'],
                                            strlen($contentBytes),
                                            $contentBytes
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            usort($emails, function($a, $b) {
                return strtotime($a['date_received']) - strtotime($b['date_received']);
            });

            return $emails;

        } catch (RequestException $e) {
            $this->errorLogController->logError('Erro ao listar e-mails por conversação: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar e-mails por conversação: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao listar e-mails por conversação: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao listar e-mails por conversação: ' . $e->getMessage());
        }
    }
}
