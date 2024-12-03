<?php

namespace App\Services;

use App\Models\FolderAssociation;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Message\EmailAddress;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailFolder;
use App\Helpers\EncryptionHelper;
use App\Services\RabbitMQService;
use App\Services\WebhookService;
use App\Controllers\ErrorLogController;
use Exception;
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
    }

    

    public function startConsumer($user_id, $email_id)
    {
        try{
        $this->syncEmailsByUserIdAndProviderId($user_id, $email_id);
    } catch (Exception $e) {
        $this->errorLogController->logError("Erro no startConsumer: " . $e->getMessage(), __FILE__, __LINE__, $user_id);
        throw $e; // Relance a exceção se quiser que seja capturada pelo catch externo
    }
    }

    public function reconnectRabbitMQ() {
        if (!$this->db || !$this->db->isConnected()) {
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
        error_log("Iniciando o consumidor RabbitMQ para sincronização de e-mails...");

        $callback = function ($msg) use ($user_id, $provider_id, $queue_name) {
            error_log("Recebida tarefa de sincronização: " . $msg->body);
            $task = json_decode($msg->body, true);

            if (!$task) {
                error_log("Erro ao decodificar a mensagem da fila.");
                $msg->nack(false, true);
                return;
            }

            try {

                if($task['is_basic']){

                $this->syncEmails(
                    $task['user_id'],
                    $task['email_account_id'],
                    $task['provider_id'],
                    $task['email'],
                    $task['imap_host'],
                    $task['imap_port'],
                    $task['password']
                );
            }   else if($task['provider_id'] == 3){ 
                $this->outlookOAuth2Service->syncEmailsOutlook($task['user_id'],$task['email_account_id'], $task['provider_id']);

            }   else if ($task['provider_id'] == 1){
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
                error_log("Erro ao sincronizar e-mails: " . $e->getMessage());
                $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
                $msg->nack(false, true);
                throw $e;
            }
        };

        try {
            error_log("Aguardando nova mensagem na fila: " . $queue_name);
            $this->rabbitMQService->consumeQueue($queue_name, $callback);
        } catch (Exception $e) {
            error_log("Erro ao consumir a fila RabbitMQ: " . $e->getMessage());
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
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
    set_time_limit(0);

    $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);

    if (!$emailAccount) {
        error_log("Conta de e-mail não encontrada para user_id={$user_id} e email_id={$email_id}");
        $this->errorLogController->logError("Conta de e-mail não encontrada para user_id={$user_id} e email_id={$email_id}", __FILE__, __LINE__, $user_id);
        
        return json_encode(['status' => false, 'message' => 'Conta de e-mail não encontrada.']);
    }

    $queue_name = $this->generateQueueName($user_id, $emailAccount['provider_id']);

    error_log("Conta de e-mail encontrada: " . $emailAccount['email']);
    error_log("Senha Descriptografada: " . EncryptionHelper::decrypt($emailAccount['password']));


        $message = [
            'user_id' => $user_id,
            'provider_id' => $emailAccount['provider_id'],
            'email_account_id' => $emailAccount['id'],
            'email' => $emailAccount['email'],
            'password' => EncryptionHelper::decrypt($emailAccount['password']),
            'oauth2_token' => $emailAccount['oauth_token'] ?? null,
            'refresh_token' => $emailAccount['refresh_token'] ?? null,
            'client_id' => $emailAccount['client_id'] ?? null,
            'client_secret' => $emailAccount['client_secret'] ?? null,
            'tenant_id' => $emailAccount['tenant_id'] ?? null,
            'auth_code' => $emailAccount['auth_code'] ?? null,
            'is_basic' => $emailAccount['is_basic'] ?? 0,
            'imap_host' => $emailAccount['imap_host'],
            'imap_port' => $emailAccount['imap_port'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);

        $this->consumeEmailSyncQueue($user_id, $emailAccount['provider_id'], $queue_name);

        return json_encode(['status' => true, 'message' => 'Sincronização de e-mails iniciada com sucesso.']);
    } catch (Exception $e) {
        error_log("Erro ao adicionar tarefa de sincronização no RabbitMQ: " . $e->getMessage());
        $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
        
        return json_encode(['status' => false, 'message' => 'Erro ao iniciar a sincronização de e-mails.']);
    }
}


    private function generateQueueName($user_id, $provider_id)
    {
        return 'email_sync_queue_' . $user_id . '_' . $provider_id . '_' . time();
    }

    private function syncEmails($user_id, $email_account_id, $provider_id, $email, $imap_host, $imap_port, $password)
    {
        error_log("Sincronizando e-mails para o usuário $user_id e provedor $provider_id");
    
        try {
            $server = new Server($imap_host, $imap_port);
            $connection = $server->authenticate($email, $password);

            $this->errorLogController->logError(
                "Iniciando associações: " . "Para email" . $email_account_id,
                __FILE__,
                __LINE__,
                $user_id
            );

            $associationsResponse = $this->folderAssociationModel->getAssociationsByEmailAccount($email_account_id);

            $this->errorLogController->logError(
                "Associações: " . json_encode($associationsResponse['Data'], JSON_PRETTY_PRINT),
                __FILE__,
                __LINE__,
                $user_id
            );
            
            if ($associationsResponse['Status'] === 'Success') {
                $associations = $associationsResponse['Data'];
            } else {
                // Trata o erro se a resposta não for bem-sucedida
                $this->errorLogController->logError(
                    "Falha ao recuperar associações: " . $associationsResponse['Message'],
                    __FILE__,
                    __LINE__,
                    $user_id
                );
                $associations = []; // Define como um array vazio para evitar erros posteriores
            }
            
            $processedFolders = ['INBOX_PROCESSED', 'SPAM_PROCESSED', 'TRASH_PROCESSED'];
            
            foreach ($processedFolders as $processedFolder) {
                if (!$connection->hasMailbox($processedFolder)) {
                    $connection->createMailbox($processedFolder);
                }
            }
            
            foreach (['INBOX', 'SPAM', 'TRASH'] as $folderType) {
                // Filtra as associações pelo tipo de pasta
                $filteredAssociations = array_filter($associations, function ($assoc) use ($folderType) {
                    return $assoc['folder_type'] === $folderType;
                });
            
                $this->errorLogController->logError(
                    "Filtro de associação: " . json_encode($filteredAssociations, JSON_PRETTY_PRINT),
                    __FILE__,
                    __LINE__,
                    $user_id
                );
            
                if (!empty($filteredAssociations)) {
                    $association = current($filteredAssociations);
            
                    $originalFolderName = $association['folder_name'];
                    $associatedFolderName = $association['associated_folder_name'];
            
                    $originalMailbox = $connection->getMailbox($originalFolderName);
            
                    $this->errorLogController->logError(
                        "originalMailbox: " . json_encode($originalMailbox, JSON_PRETTY_PRINT),
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
            
                    $associatedMailbox = $connection->getMailbox($associatedFolderName);
            
                    $this->errorLogController->logError(
                        "associatedMailbox: " . json_encode($associatedMailbox, JSON_PRETTY_PRINT),
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
            
                    $messages = $originalMailbox->getMessages();

                    
                    $this->errorLogController->logError(
                        "Mensagens: " . json_encode($messages, JSON_PRETTY_PRINT),
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
            
                    // Remove mensagens duplicadas
                    $uniqueMessages = [];
                    foreach ($messages as $message) {
                        $messageId = $message->getId();
                        if (!isset($uniqueMessages[$messageId])) {
                            $uniqueMessages[$messageId] = $message;
                        }
                    }
                    $messages = array_values($uniqueMessages);

            
                    $this->errorLogController->logError(
                        "Mensagens únicas: " . json_encode($messages, JSON_PRETTY_PRINT),
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
            
                    foreach ($messages as $key => $message) {
                        try {
                            if (!$message->isValid() || !$message->hasValidHeaders()) {
                                continue; 
                            }

                            $message->move($associatedMailbox);
            
                            $this->errorLogController->logError(
                                "E-mail {$message->getId()} movido para a pasta associada $associatedFolderName.",
                                __FILE__,
                                __LINE__,
                                $user_id
                            );
            
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
                } else {
                    // Nenhuma associação encontrada para este tipo de pasta
                    $this->errorLogController->logError(
                        "Nenhuma associação encontrada para " . $folderType,
                        __FILE__,
                        __LINE__,
                        $user_id
                    );
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
                        $fromAddress = $message->getFrom()->getAddress();
                        $fromName = $message->getFrom()->getName() ?? null;
                        $subject = $message->getSubject() ?? 'Sem Assunto';
                        $date_received = $message->getDate()->format('Y-m-d H:i:s');
                        $isRead = $message->isSeen() ? 1 : 0;
                        $body_html = $message->getBodyHtml();
                        $body_text = $message->getBodyText();
    
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
    
                        // Processamento de anexos
                        if ($message->hasAttachments()) {
                            $attachments = $message->getAttachments();
                            foreach ($attachments as $attachment) {
                                try {
                                    $filename = $attachment->getFilename();
                                    if (is_null($filename) || empty($filename)) {
                                        error_log("Anexo ignorado: o nome do arquivo está nulo.");
                                        continue;
                                    }
                                    $mimeTypeName = $attachment->getType();
                                    $subtype = $attachment->getSubtype();
                                    $fullMimeType = $mimeTypeName . '/' . $subtype;
                                    $contentBytes = $attachment->getDecodedContent();
                                    if ($contentBytes === false) {
                                        error_log("Falha ao obter o conteúdo do anexo: $filename");
                                        continue;
                                    }
                                    $this->emailModel->saveAttachment(
                                        $emailId,
                                        $filename,
                                        $fullMimeType,
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
            $event = [
                'Code' => 500,
                'Status' => 'Failed',
                'Message' => 'Failed to sync emails',
                'Data' => [
                    'email_account_id' => $email_account_id,
                    'user_id' => $user_id,
                    'uuid' => uniqid(),
                ]
            ];
            $this->webhookService->triggerEvent($event, $user_id);
            error_log("Erro durante a sincronização de e-mails: " . $e->getMessage());
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw $e;
        }
        return;
    }
    
    
    
    
}
