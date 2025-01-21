<?php

namespace App\Services;

use App\Models\FolderAssociation;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Message\EmailAddress;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailFolder;
use App\Helpers\EncryptionHelper;
use App\Services\RabbitMQService;
use App\Services\WebhookService;
use App\Controllers\ErrorLogController;
use Exception;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use App\Models\AwsCredential;

class EmailSyncService
{
    private $emailModel;
    private $emailAccountModel;
    private $rabbitMQService;
    private $webhookService;
    private $errorLogController; 
    private $db;
    
    private $folderAssociationModel;
        
    private $outlookOAuth2Service;

    private $gmailOauth2Service;

    private $emailFolderModel;

    private $isGeneratingToken = false; 

    private $s3Client;
    private $bucketName = 'vdkmail';

    public function __construct($db)
    {
        $this->db = $db;
        $this->emailModel = new Email($db);  
        $this->emailAccountModel = new EmailAccount($db);
        $this->rabbitMQService = new RabbitMQService($db);
        $this->webhookService = new WebhookService();
        $this->errorLogController = new ErrorLogController();
        $this->outlookOAuth2Service  = new OutlookOAuth2Service();
        $this->gmailOauth2Service = new GmailOAuth2Service();
        $this->emailFolderModel = new EmailFolder($db);
        $this->folderAssociationModel = new FolderAssociation($db);

        // Buscar credenciais do banco
        $awsCredentialModel = new AwsCredential($db);
        $credentials = $awsCredentialModel->getCredentials();

        if (!$credentials) {
            throw new Exception("Credenciais AWS não encontradas no banco de dados");
        }

        $s3Config = [
            'version' => 'latest',
            'region'  => $credentials['region'],
            'credentials' => [
                'key'    => $credentials['access_key_id'],
                'secret' => $credentials['secret_access_key'],
            ]
        ];

        if (!empty($credentials['endpoint'])) {
            $s3Config['endpoint'] = $credentials['endpoint'];
        }

        try {
            $this->s3Client = new S3Client($s3Config);
            error_log("Cliente S3 inicializado com sucesso");
            
            // Teste de conexão
            $result = $this->s3Client->listBuckets();
            error_log("Buckets disponíveis: " . json_encode($result['Buckets']));
        } catch (AwsException $e) {
            error_log("Erro AWS: " . $e->getMessage());
            error_log("AWS Error Code: " . $e->getAwsErrorCode());
            error_log("AWS Error Type: " . $e->getAwsErrorType());
            error_log("AWS Request ID: " . $e->getAwsRequestId());
            throw $e;
        } catch (Exception $e) {
            error_log("Erro geral ao inicializar S3: " . $e->getMessage());
            throw $e;
        }
    }

    

    public function startConsumer($user_id, $email_id)
    {
        try {
            $this->errorLogController->logError(
                "Iniciando startConsumer",
                __FILE__,
                __LINE__,
                $user_id,
                ['email_id' => $email_id]
            );
            
            $this->syncEmailsByUserIdAndProviderId($user_id, $email_id);
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro no startConsumer",
                __FILE__,
                __LINE__,
                $user_id,
                [
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    public function reconnectRabbitMQ() {
        if (!$this->db || !$this->db->ping()) {
            $this->rabbitMQService->connect();
        } 
    }

    public function updateTokens($emailAccountId, $access_token, $refresh_token = null)
{
    try {
        $this->emailAccountModel->updateTokens( 
            $emailAccountId,
            $access_token,
            $refresh_token
        );

        error_log("Access token e refresh token atualizados com sucesso para a conta de e-mail ID: $emailAccountId");

    } catch (Exception $e) {
        error_log("Erro ao atualizar os tokens: " . $e->getMessage());
        $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $emailAccountId);
        throw $e;
    }
}

    public function consumeEmailSyncQueue($user_id, $provider_id, $queue_name)
    {
        $this->errorLogController->logError(
            "Iniciando consumeEmailSyncQueue",
            __FILE__,
            __LINE__,
            $user_id,
            [
                'provider_id' => $provider_id,
                'queue_name' => $queue_name
            ]
        );

        try {
            // Verifica se a fila existe
            $this->errorLogController->logError(
                "Verificando existência da fila",
                __FILE__,
                __LINE__,
                $user_id,
                ['queue_name' => $queue_name]
            );

            $callback = function ($msg) use ($user_id, $provider_id, $queue_name) {
                $this->errorLogController->logError(
                    "Callback iniciado para mensagem",
                    __FILE__,
                    __LINE__,
                    $user_id,
                    ['message_body' => $msg->body]
                );

                try {
                    $task = json_decode($msg->body, true);
                    
                    $this->errorLogController->logError(
                        "Mensagem decodificada",
                        __FILE__,
                        __LINE__,
                        $user_id,
                        ['task' => $task]
                    );

                    if($task['is_basic'] == 1) {
                        $this->errorLogController->logError(
                            "Iniciando sincronização básica",
                            __FILE__,
                            __LINE__,
                            $user_id,
                            $task
                        );
                        
                        $this->syncEmails(
                            $task['user_id'],
                            $task['email_account_id'],
                            $task['provider_id'],
                            $task['email'],
                            $task['imap_host'],
                            $task['imap_port'],
                            $task['password']
                        );
                    }   else if($task['provider_id'] == 1){ 
                        $this->outlookOAuth2Service->syncEmailsOutlook($task['user_id'],$task['email_account_id']);

                    }   else if ($task['provider_id'] == 2){
                        $this->gmailOauth2Service->syncEmailsGmail($task['user_id'],$task['email_account_id'], $task['provider_id']);
                    }

                    $msg->ack();
                    error_log("Sincronização concluída para a mensagem na fila.");

                    if ($this->rabbitMQService->markJobAsExecuted($queue_name)) {
                        error_log("Job marcado como executado com sucesso.");
                    } else {
                        error_log("Erro ao marcar o job como executado.");
                    }

                    $this->syncEmailsByUserIdAndProviderId($task['user_id'], $task['email_account_id']);

                } catch (Exception $e) {
                    $this->errorLogController->logError(
                        "Erro no callback",
                        __FILE__,
                        __LINE__,
                        $user_id,
                        [
                            'error' => $e->getMessage(),
                            'stack_trace' => $e->getTraceAsString()
                        ]
                    );
                    $msg->nack(false, false);
                }
            };

            $this->rabbitMQService->consumeQueue($queue_name, $callback);
            
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao consumir fila",
                __FILE__,
                __LINE__,
                $user_id,
                [
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                    'queue_name' => $queue_name
                ]
            );
            throw $e;
        }
    }


    public function getEmailAccountByUserIdAndProviderId($user_id, $provider_id)
    {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);

            if (!$emailAccount) {
                throw new Exception("Conta de e-mail não encontrada para user_id={$user_id} e provider_id={$provider_id}");
            }

            return $emailAccount;
        } catch (Exception $e) {
            error_log("Erro ao buscar conta de e-mail: " . $e->getMessage());
            throw $e;
        }
    }
    

public function syncEmailsByUserIdAndProviderId($user_id, $email_id)
{
    try {
        $this->errorLogController->logError(
            "Iniciando syncEmailsByUserIdAndProviderId",
            __FILE__,
            __LINE__,
            $user_id,
            ['email_id' => $email_id]
        );

        set_time_limit(0);
        
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
        
        if (!$emailAccount) {
            $this->errorLogController->logError(
                "Conta de e-mail não encontrada",
                __FILE__,
                __LINE__,
                $user_id,
                [
                    'email_id' => $email_id,
                    'user_id' => $user_id
                ]
            );
            return json_encode(['status' => false, 'message' => 'Conta de e-mail não encontrada.']);
        }

        $this->errorLogController->logError(
            "Conta de email encontrada",
            __FILE__,
            __LINE__,
            $user_id,
            [
                'email' => $emailAccount['email'],
                'provider_id' => $emailAccount['provider_id']
            ]
        );

        // Log antes de criar a mensagem para RabbitMQ
        $this->errorLogController->logError(
            "Preparando mensagem para RabbitMQ",
            __FILE__,
            __LINE__,
            $user_id,
            ['email_account_id' => $emailAccount['id']]
        );

        $message = [
            'user_id' => $user_id,
            'email_account_id' => $emailAccount['id'],
            'provider_id' => $emailAccount['provider_id'],
            'email' => $emailAccount['email'],
            'password' => EncryptionHelper::decrypt($emailAccount['password']),
            'oauth2_token' => $emailAccount['oauth_token'] ?? null,
            'refresh_token' => $emailAccount['refresh_token'] ?? null,
            'is_basic' => $emailAccount['is_basic'] ?? 0,
            'imap_host' => $emailAccount['imap_host'],
            'imap_port' => $emailAccount['imap_port'],
        ];

        $queue_name = 'email_sync_queue_' . $user_id . '_' . $emailAccount['id'];
        
        $this->errorLogController->logError(
            "Tentando publicar mensagem no RabbitMQ",
            __FILE__,
            __LINE__,
            $user_id,
            [
                'queue_name' => $queue_name,
                'imap_host' => $emailAccount['imap_host'],
                'imap_port' => $emailAccount['imap_port']
            ]
        );

        try {
            $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);
            
            $this->errorLogController->logError(
                "Mensagem publicada com sucesso no RabbitMQ",
                __FILE__,
                __LINE__,
                $user_id,
                ['queue_name' => $queue_name]
            );
            
            $this->consumeEmailSyncQueue($user_id, $emailAccount['provider_id'], $queue_name);
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Falha ao publicar mensagem no RabbitMQ",
                __FILE__,
                __LINE__,
                $user_id,
                ['queue_name' => $queue_name, 'error' => $e->getMessage()]
            );
        }

    } catch (Exception $e) {
        $this->errorLogController->logError(
            "Erro fatal em syncEmailsByUserIdAndProviderId",
            __FILE__,
            __LINE__,
            $user_id,
            [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'email_id' => $email_id
            ]
        );
        throw $e;
    }
}


    private function generateQueueName($user_id, $provider_id)
    {
        return 'email_sync_queue_' . $user_id . '_' . $provider_id . '_' . time();
    }

    private function syncEmails($user_id, $email_account_id, $provider_id, $email, $imap_host, $imap_port, $password)
    {
        try {
            $this->errorLogController->logError(
                "Iniciando sincronização de emails",
                __FILE__,
                __LINE__,
                $user_id,
                [
                    'email_account_id' => $email_account_id,
                    'provider_id' => $provider_id,
                    'imap_host' => $imap_host,
                    'imap_port' => $imap_port
                ]
            );

            $server = new Server($imap_host, $imap_port);
            $connection = $server->authenticate($email, $password);


            $associationsResponse = $this->folderAssociationModel->getAssociationsByEmailAccount($email_account_id);

            
            if ($associationsResponse['Status'] === 'Success') {
                $associations = $associationsResponse['Data'];
            } else {
                $associations = []; 
            }
            
            $processedFolders = ['INBOX_PROCESSED', 'SPAM_PROCESSED', 'TRASH_PROCESSED'];
            
            foreach ($processedFolders as $processedFolder) {
                if (!$connection->hasMailbox($processedFolder)) {
                    $connection->createMailbox($processedFolder);
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
            
                    $originalMailbox = $connection->getMailbox($originalFolderName);
            
                    $associatedMailbox = $connection->getMailbox($associatedFolderName);
            
                    $messages = $originalMailbox->getMessages();
                    
                    $uniqueMessages = [];
                    foreach ($messages as $message) {
                        $messageId = $message->getId();
                        if (!isset($uniqueMessages[$messageId])) {
                            $uniqueMessages[$messageId] = $message; 
                        }
                    }
                    $messages = array_values($uniqueMessages);
                    
                    foreach ($messages as $key => $message) {
                        try {

                            $message->move($associatedMailbox);
            

                            $this->emailModel->deleteEmail($message->getId());
            
                            unset($messages[$key]);
            
                            error_log("E-mail {$message->getId()} movido da pasta $originalFolderName para $associatedFolderName.");
                        } catch (Exception $e) {
                            $this->errorLogController->logError(
                                "Erro ao mover e-mail {$message->getId()} para a pasta associada $associatedFolderName: " . $e->getMessage(),
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
                            error_log("Erro ao mover e-mail {$message->getId()} da pasta $originalFolderName para $associatedFolderName: " . $e->getMessage());
                        }
                    }
            
                    $imapStream = $connection->getResource()->getStream();
                    imap_expunge($imapStream);
                }
            }
            
            
    
            $mailboxes = $connection->getMailboxes(); 
            $folderNames = [];
            foreach ($mailboxes as $mailbox) {
                if (!($mailbox->getAttributes() & \LATT_NOSELECT)) {
                    $folderNames[] = $mailbox->getName(); 
                }
            }
            $folders = $this->emailFolderModel->syncFolders($email_account_id, $folderNames); 
    
            foreach ($mailboxes as $mailbox) {
                if ($mailbox->getAttributes() & \LATT_NOSELECT) {
                    error_log("Ignorando a pasta de sistema: " . $mailbox->getName());
                    continue;
                }
    
                $folderName = $mailbox->getName();
    
                if (!isset($folders[$folderName])) {
                    error_log("Pasta " . $folderName . " não está sincronizada no banco de dados. Ignorando...");
                    continue;
                }
    
                $folderId = $folders[$folderName];
                $messages = $mailbox->getMessages();
    
                $storedMessageIds = $this->emailModel->getEmailIdsByFolderId($user_id, $folderId);
                $processedMessageIds = []; 
    
                foreach ($messages as $message) {
                    try {
                        $messageId = $message->getId();
                        $processedMessageIds[] = $messageId;
                        $fromAddress = $message->getFrom()->getAddress() ?? 'unknown@example.com';
                        $fromName = $message->getFrom()->getName() ?? 'Unknown Sender';
                        $subject = $message->getSubject() ?? 'Sem Assunto';
                        $subject = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $subject);
                        $subject = mb_convert_encoding($subject, 'UTF-8', 'auto');
                        $date_received = $message->getDate()->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s');
                        $isRead = $message->isSeen() ? 1 : 0;
                        
                        $body_html = $message->getBodyHtml();
                        $body_text = $message->getBodyText();

                        $this->logDebug("Body HTML original: " . $body_html);
                        
                        if (preg_match_all('/src=["\']cid:([^"\']+)["\']/i', $body_html, $matches)) {
                            $cidsToFind = $matches[1];
                            $this->logDebug("CIDs para encontrar: " . json_encode($cidsToFind));
                            
                            foreach ($message->getAttachments() as $attachment) {
                                $structure = $attachment->getStructure();
                                $contentId = isset($structure->id) ? trim($structure->id, '<>') : null;
                                
                                if ($contentId && in_array($contentId, $cidsToFind)) {
                                    $this->logDebug("Encontrado anexo para CID: " . $contentId);
                                    
                                    $content = $attachment->getDecodedContent();
                                    $base64Content = base64_encode($content);
                                    
                                    $mimeType = "image/" . strtolower($structure->subtype);
                                    
                                    $pattern = '/src=["\']cid:' . preg_quote($contentId, '/') . '["\']/i';
                                    $replacement = 'src="data:' . $mimeType . ';base64,' . $base64Content . '"';
                                    $body_html = preg_replace($pattern, $replacement, $body_html);
                                }
                            }
                        } else {
                            $this->logDebug("Nenhum CID encontrado no HTML com regex"); 
                        }

                        $bcc = $message->getBcc();
                        if ($bcc && count($bcc) > 0) {
                            error_log("E-mail contém CCO (BCC). Ignorando o processamento.");
                            continue;
                        }
    
                        if (!$messageId || !$fromAddress) {
                            error_log("E-mail com Message-ID ou From nulo. Ignorando...");
                            continue;
                        }
    
                        $existingEmail = $this->emailModel->emailExistsByMessageId($messageId, $email_account_id);
                        if ($existingEmail) {
                            error_log("E-mail com Message-ID $messageId já foi processado. Ignorando...");
                            continue;
                        }
    
                        $inReplyTo = $message->getInReplyTo();
                        if (is_array($inReplyTo)) {
                            $inReplyTo = implode(', ', $inReplyTo);
                        }
                        $references = implode(', ', $message->getReferences());
    
                        $ccAddresses = $message->getCc();
                        $cc = $ccAddresses ? implode(', ', array_map(fn(EmailAddress $addr) => $addr->getAddress(), $ccAddresses)) : null;
    
                        $conversation_id = null;
                        $conversation_step = 1; 

                        if (!empty($references)) {
                            $referenceCount = count(explode(', ', $references));
                            $conversation_step = $referenceCount + 1;
                        }
                
                        $conversation_id = $references ? explode(', ', $references)[0] : $messageId;

                        if ($message->hasAttachments()) {
                            $attachments = $message->getAttachments();
                            $this->errorLogController->logError(
                                "Processando anexos",
                                __FILE__,
                                __LINE__,
                                $user_id,
                                [
                                    'message_id' => $message->getId(),
                                    'num_attachments' => count($attachments)
                                ]
                            );

                            $cidMap = [];
                            
                            error_log("Processando " . count($attachments) . " anexos");
                        
                            if (preg_match_all('/src=["\']cid:([^"\']+)["\']/i', $body_html, $matches)) {
                                $cidsToFind = $matches[1];
                                error_log("CIDs para procurar: " . implode(', ', $cidsToFind));
                                
                                // Busca apenas os anexos que correspondem aos CIDs encontrados
                                foreach ($attachments as $attachment) {
                                    $structure = $attachment->getStructure();
                                    $this->logDebug("Estrutura do anexo: " . print_r($structure, true));
                                    $contentId = $attachment->getParameters()->get('content-id');
                                    if ($contentId) {
                                        $contentId = trim($contentId, '<>');
                                        
                                        if (in_array($contentId, $cidsToFind)) {
                                            try {
                                                $result = $this->processCidImage($attachment, $email_account_id, $contentId);
                                                $cidMap[$contentId] = $result['url'];
                                                error_log("Encontrado anexo correspondente para CID: $contentId");
                                            } catch (Exception $e) {
                                                $this->errorLogController->logError(
                                                    "Erro ao processar imagem CID: " . $e->getMessage(),
                                                    __FILE__,
                                                    __LINE__
                                                );
                                            }
                                        }
                                    }
                                }
                            }

                            if (!empty($cidMap)) {
                                error_log("Substituindo " . count($cidMap) . " CIDs no corpo do email");
                                foreach ($cidMap as $cid => $url) {
                                    $pattern = '/src=["\']cid:' . preg_quote($cid, '/') . '["\']|cid:' . preg_quote($cid, '/') . '/i';
                                    $replacement = 'src="' . $url . '"';
                                    $body_html = preg_replace($pattern, $replacement, $body_html);
                                    error_log("CID substituído: $cid");
                                }
                            }

                            foreach ($attachments as $attachment) {
                                $filename = $attachment->getFilename();
                                
                                $contentId = $attachment->getParameters()->get('content-id');
                                if ($contentId && isset($cidMap[trim($contentId, '<>')])) {
                                    continue;
                                }

                                try {
                                    $content = $attachment->getDecodedContent();
                                    $contentType = $attachment->getType() . '/' . $attachment->getSubtype();
                                    $contentHash = hash('sha256', $content);
                                    $s3Key = "attachments/{$contentHash}/{$filename}";

                                    $this->errorLogController->logError(
                                        "Tentando upload para S3",
                                        __FILE__,
                                        __LINE__,
                                        $user_id,
                                        [
                                            'filename' => $filename,
                                            'content_type' => $contentType,
                                            'content_hash' => $contentHash,
                                            's3_key' => $s3Key,
                                            'size' => strlen($content)
                                        ]
                                    );

                                    try {
                                        $result = $this->s3Client->putObject([
                                            'Bucket' => $this->bucketName,
                                            'Key'    => $s3Key,
                                            'Body'   => $content,
                                            'ContentType' => $contentType,
                                            'Metadata' => [
                                                'content_hash' => $contentHash,
                                                'original_filename' => $filename
                                            ]
                                        ]);

                                        $this->errorLogController->logError(
                                            "Upload S3 bem sucedido",
                                            __FILE__,
                                            __LINE__,
                                            $user_id,
                                            [
                                                's3_key' => $s3Key,
                                                'result' => json_encode($result)
                                            ]
                                        );

                                    } catch (Exception $e) {
                                        $this->errorLogController->logError(
                                            "Erro no upload S3",
                                            __FILE__,
                                            __LINE__,
                                            $user_id,
                                            [
                                                'error' => $e->getMessage(),
                                                'filename' => $filename,
                                                's3_key' => $s3Key,
                                                'stack_trace' => $e->getTraceAsString()
                                            ]
                                        );
                                        throw $e;
                                    }

                                } catch (Exception $e) {
                                    $this->errorLogController->logError(
                                        "Erro ao processar anexo",
                                        __FILE__,
                                        __LINE__,
                                        $user_id,
                                        [
                                            'error' => $e->getMessage(),
                                            'filename' => $attachment->getFilename(),
                                            'stack_trace' => $e->getTraceAsString()
                                        ]
                                    );
                                }
                            }
                        }
                        
                
    
                        $emailId = $this->emailModel->saveEmail(
                            $user_id, 
                            $email_account_id,
                            $messageId,
                            $subject,
                            $fromAddress,
                            implode(', ', array_map(fn(EmailAddress $addr) => $addr->getAddress(), iterator_to_array($message->getTo()))),
                            $body_html, 
                            $body_text,
                            $date_received,
                            $references,
                            $inReplyTo,
                            $isRead,
                            $folderId, 
                            $cc,
                            $uidCounter,
                            $conversation_id,
                            $conversation_step,
                            $fromName
                             
                        );
    
                        if ($body_html) {
                            preg_match_all('/<img[^>]+src="data:image\/([^;]+);base64,([^"]+)"/', $body_html, $matches, PREG_SET_ORDER);
    
                            foreach ($matches as $match) {
                                try {
                                    $imageType = $match[1];
                                    $base64Data = $match[2];
                                    $decodedContent = base64_decode($base64Data);
    
                                    if ($decodedContent !== false) {
                                        $contentHash = hash('sha256', $decodedContent);
                                        $filename = uniqid("inline_img_") . '.' . $imageType;
                                        $fullMimeType = 'image/' . $imageType;
    
                                        $s3Key = "attachments/" . $contentHash . "/" . $filename;
    
                                        $this->emailModel->saveAttachment(
                                            $emailId,
                                            $filename,
                                            $fullMimeType,
                                            strlen($decodedContent),
                                            $s3Key,
                                            $contentHash
                                        );
                                    }
                                } catch (Exception $e) {
                                    $this->errorLogController->logError("Erro ao processar imagem embutida: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                                }
                            }
                        }
    
                       
    
                        $event = [
                            'type' => 'email_received',
                            'Status' => 'Success',
                            'Message' => 'Email received successfully',
                            'Data' => [
                                'email_account_id' => $email_account_id,
                                'email_id' => $emailId,
                                'message_id' => $messageId,
                                'subject' => $subject,
                                'from' => $fromAddress,
                                'to' => array_map(fn(EmailAddress $addr) => $addr->getAddress(), iterator_to_array($message->getTo())),
                                'received_at' => $date_received,
                                'user_id' => $user_id,
                                'folder_id' => $folderId,
                                'uuid' => uniqid(),
                            ]
                        ];

                        $this->webhookService->triggerEvent($event, $user_id);
                        $uidCounter++;
                    } catch (Exception $e) {
                        $this->errorLogController->logError("Erro ao processar e-mail: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
                    }
                }
    
                $deletedMessageIds = array_diff($storedMessageIds, $processedMessageIds);
                foreach ($deletedMessageIds as $deletedMessageId) {
                    $this->emailModel->deleteEmailByMessageId($deletedMessageId, $user_id);
                    error_log("E-mail com Message-ID $deletedMessageId foi deletado no servidor e removido do banco de dados.");
                }
    
                error_log("Sincronização de e-mails concluída para a pasta " . $folderName);
            }
            
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro fatal na sincronização",
                __FILE__,
                __LINE__,
                $user_id,
                [
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
        return;
    }

    private function attachmentExists($email_account_id, $filename) {
        $existingAttachment = $this->emailModel->attachmentExists($email_account_id, $filename);
        return $existingAttachment !== null; 
    }

    private function replaceCidWithBase64($body_html, $attachment, $emailId) {
        $contentBytes = $attachment->getDecodedContent();
        $base64Content = base64_encode($contentBytes);
        
        $mimeTypeName = $attachment->getType();
        $subtype = $attachment->getSubtype();
        $fullMimeType = $mimeTypeName . '/' . $subtype;

        $contentId = trim($attachment->getContentId(), '<>');
        $contentHash = hash('sha256', $contentBytes);
        $filename = 'cid_' . $contentId . '.' . strtolower($subtype);
        $s3Key = "inline-images/{$contentHash}/{$filename}";

        try {
            $this->emailModel->saveAttachment(
                $emailId,
                $filename,
                $fullMimeType,
                strlen($contentBytes),
                $s3Key,
                $contentHash
            );
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao salvar imagem CID como anexo: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
        }

        $pattern = '/src="cid:([^"]+)"/i';  
        $replacement = 'src="data:' . $fullMimeType . ';base64,' . $base64Content . '"';
        
        return preg_replace($pattern, $replacement, $body_html);
    }
    
    private function logDebug($message) {
        try {
            $logDir = __DIR__ . '/../../logs';
            $logPath = $logDir . '/email_sync.log';
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
            file_put_contents($logPath, $logMessage, FILE_APPEND);
        } catch (Exception $e) {
            error_log("Erro ao escrever no arquivo de log: " . $e->getMessage());
        }
    }

    private function processAttachment($attachment, $email_account_id)
    {
        try {
            $content = $attachment->getDecodedContent();
            $filename = $attachment->getFilename();
            $contentType = $attachment->getType() . '/' . $attachment->getSubtype();
            
            $contentHash = hash('sha256', $content);
            
            $existingAttachment = $this->emailModel->getAttachmentByHash($contentHash);
            
            if ($existingAttachment) {
                return [
                    's3_key' => $existingAttachment['s3_key'],
                    'content_hash' => $contentHash
                ];
            }
            
            $s3Key = "attachments/{$contentHash}/{$filename}";
            
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key'    => $s3Key,
                'Body'   => $content,
                'ContentType' => $contentType,
                'Metadata' => [
                    'content_hash' => $contentHash,
                    'original_filename' => $filename
                ]
            ]);
            
            $this->emailModel->saveAttachment( 
                $email_account_id,
                $filename,
                $contentType,
                strlen($content),
                $s3Key,
                $contentHash
            );
            
            return [
                's3_key' => $s3Key,
                'content_hash' => $contentHash
            ];
            
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Error processing attachment: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }

    private function processCidImage($attachment, $email_account_id, $contentId)
    {
        try {
            error_log("Iniciando processamento de imagem CID: " . $contentId);
            
            $content = $attachment->getDecodedContent();
            $contentHash = hash('sha256', $content);
            $filename = 'cid_' . $contentId . '.' . strtolower($attachment->getSubtype());
            $s3Key = "attachments/{$contentHash}/{$filename}";
            
            error_log("Tentando upload para S3: " . $s3Key);
            
            try {
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucketName,
                    'Key'    => $s3Key,
                    'Body'   => $content,
                    'ContentType' => $attachment->getType() . '/' . $attachment->getSubtype(),
                    'Metadata' => [
                        'content_hash' => $contentHash,
                        'original_filename' => $filename
                    ]
                ]);
                
                error_log("Upload S3 bem sucedido: " . json_encode($result));
                
                return [
                    'url' => $result['ObjectURL'],
                    'key' => $s3Key
                ];
                
            } catch (AwsException $e) {
                error_log("Erro AWS: " . $e->getMessage());
                error_log("AWS Error Code: " . $e->getAwsErrorCode());
                error_log("AWS Error Type: " . $e->getAwsErrorType());
                error_log("AWS Request ID: " . $e->getAwsRequestId());
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Erro ao processar imagem CID: " . $e->getMessage());
            throw $e;
        }
    }
}
