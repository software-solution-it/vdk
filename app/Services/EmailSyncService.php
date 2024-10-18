<?php

namespace App\Services;

use Ddeboer\Imap\Server;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Message\EmailAddress;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Helpers\EncryptionHelper;
use App\Services\RabbitMQService;
use App\Services\WebhookService;
use Exception;

class EmailSyncService
{
    private $emailModel;
    private $emailAccountModel;
    private $rabbitMQService;
    private $webhookService;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
        $this->rabbitMQService = new RabbitMQService($db);
        $this->webhookService = new WebhookService($db);
    }

    public function startConsumer($user_id, $provider_id)
    {
        $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);
    
    }
    

    public function reconnectRabbitMQ() {
        // Verifica se a conexão está fechada e reconecta
        if (!$this->db || !$this->db->isConnected()) {
            $this->rabbitMQService->connect();
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
                // Sincroniza os e-mails conforme os dados da fila
                $this->syncEmails(
                    $task['user_id'],
                    $task['provider_id'],
                    $task['email'],
                    $task['imap_host'],
                    $task['imap_port'],
                    $task['password']
                );
    
                // Confirma a mensagem como processada
                $msg->ack();
    
                error_log("Sincronização concluída para a mensagem na fila.");
    

                if ($this->rabbitMQService->markJobAsExecuted($queue_name)) {
                    error_log("Job marcado como executado com sucesso.");
                } else {
                    error_log("Erro ao marcar o job como executado.");
                }
    
                // Cria uma nova fila para continuar a sincronização
                $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);
    
            } catch (Exception $e) {
                error_log("Erro ao sincronizar e-mails: " . $e->getMessage());
                $msg->nack(false, true);
            }
        };
    
        try {
            error_log("Aguardando nova mensagem na fila: " . $queue_name);
    
            // Consome uma única mensagem por vez e processa antes de consumir a próxima
            $this->rabbitMQService->consumeQueue($queue_name, $callback);
    
        } catch (Exception $e) {
            error_log("Erro ao consumir a fila RabbitMQ: " . $e->getMessage());
        }
    }
    
    public function syncEmailsByUserIdAndProviderId($user_id, $provider_id)
    {
        set_time_limit(0);

        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);

        if (!$emailAccount) {
            error_log("Conta de e-mail não encontrada para user_id={$user_id} e provider_id={$provider_id}");
            return;
        }

        $queue_name = $this->generateQueueName($user_id, $provider_id);

        error_log("Conta de e-mail encontrada: " . $emailAccount['email']);
        error_log("Senha Descriptografada: " . EncryptionHelper::decrypt($emailAccount['password']));

        try {
            $message = [
                'user_id' => $user_id,
                'provider_id' => $provider_id,
                'email' => $emailAccount['email'],
                'imap_host' => $emailAccount['imap_host'],
                'imap_port' => $emailAccount['imap_port'],
                'password' => EncryptionHelper::decrypt($emailAccount['password'])
            ];

            $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);

            $this->consumeEmailSyncQueue($user_id, $provider_id, $queue_name);

        } catch (Exception $e) {
            error_log("Erro ao adicionar tarefa de sincronização no RabbitMQ: " . $e->getMessage());
        }
    }

    private function generateQueueName($user_id, $provider_id)
    {
        return 'email_sync_queue_' . $user_id . '_' . $provider_id . '_' . time();
    }

    

    private function syncEmails($user_id, $provider_id, $email, $imap_host, $imap_port, $password)
    {
        error_log("Sincronizando e-mails para o usuário $user_id e provedor $provider_id");

        $lastSyncDate = $this->emailModel->getLastEmailSyncDate($user_id);
        $lastSyncDateFormatted = $lastSyncDate ? new \DateTime($lastSyncDate) : null;

        try {
            $server = new Server($imap_host, $imap_port);
            $connection = $server->authenticate($email, $password);

            $mailboxes = $connection->getMailboxes();
            foreach ($mailboxes as $mailbox) {
                if ($mailbox->getAttributes() & \LATT_NOSELECT) {
                    error_log("Ignorando a pasta de sistema: " . $mailbox->getName());
                    continue;
                }

                $lastSyncDateForFolder = $this->emailModel->getLastEmailSyncDateByFolder($user_id, $mailbox->getName());
                $lastSyncDateForFolderFormatted = $lastSyncDateForFolder ? new \DateTime($lastSyncDateForFolder) : null;

                error_log("Última data de sincronização para a pasta " . $mailbox->getName() . ": " . ($lastSyncDateForFolderFormatted ? $lastSyncDateForFolderFormatted->format('d-M-Y') : 'Sincronizando todos os e-mails'));

                $search = $lastSyncDateForFolderFormatted ? new Since($lastSyncDateForFolderFormatted) : null;

                $messages = $search ? $mailbox->getMessages($search) : $mailbox->getMessages();

                $uidCounter = 1;
                $lastEmailDate = null;

                foreach ($messages as $message) {
                    $messageId = $message->getId();
                    $fromAddress = $message->getFrom()->getAddress();
                    $subject = $message->getSubject() ?? 'Sem Assunto';
                    $date_received = $message->getDate()->format('Y-m-d H:i:s');
                    $isRead = $message->isSeen() ? 1 : 0;
                    $body = $message->getBodyHtml() ?? $message->getBodyText();

                    $bcc = $message->getBcc();
                    if ($bcc && count($bcc) > 0) {
                        error_log("E-mail contém CCO (BCC). Ignorando o processamento.");
                        continue;
                    }

                    if (!$messageId || !$fromAddress) {
                        error_log("E-mail com Message-ID ou From nulo. Ignorando...");
                        continue;
                    }

                    if ($this->emailModel->emailExistsByMessageId($messageId)) {
                        error_log("E-mail com Message-ID " . $messageId . " já existe, ignorando.");
                        continue;
                    }

                    $inReplyTo = $message->getInReplyTo();
                    if (is_array($inReplyTo)) {
                        $inReplyTo = implode(', ', $inReplyTo);
                    }

                    $references = implode(', ', $message->getReferences());


                    $ccAddresses = $message->getCc();
                    $cc = $ccAddresses ? implode(', ', array_map(fn(EmailAddress $addr) => $addr->getAddress(), $ccAddresses)) : null;

                    $emailId = $this->emailModel->saveEmail(
                        $user_id,
                        $messageId,
                        $subject,
                        $fromAddress,
                        implode(', ', array_map(fn(EmailAddress $addr) => $addr->getAddress(), iterator_to_array($message->getTo()))),
                        $body,
                        $date_received,
                        $references,
                        $inReplyTo,
                        $isRead,
                        $mailbox->getName(),
                        $cc,
                        $uidCounter
                    );


                    
                    if ($message->hasAttachments()) {
                        $attachments = $message->getAttachments();
                    
                        foreach ($attachments as $attachment) {
                            $filename = $attachment->getFilename();
                    
                            if (is_null($filename) || empty($filename)) {
                                error_log("Anexo ignorado: o nome do arquivo está nulo.");
                                continue;
                            }
                    
                            $mimeTypeName = $attachment->getType();      // Retorna o tipo principal, exemplo: "application"
                            $subtype = $attachment->getSubtype();         // Retorna o subtipo, exemplo: "pdf"
                    
                            // Concatenar o tipo e subtipo para formar o MIME type completo
                            $fullMimeType = $mimeTypeName . '/' . $subtype;
                    
                            // Obter o conteúdo do anexo em bytes
                            $contentBytes = $attachment->getContent(); // Certifique-se de que isso retorna o conteúdo correto
                    
                            // Adicione log para verificar o conteúdo antes da codificação
                            error_log("Conteúdo do anexo: " . print_r($contentBytes, true)); 
                    
                            // Verifica se o conteúdo foi recuperado corretamente
                            if ($contentBytes === false) {
                                error_log("Falha ao obter o conteúdo do anexo: $filename");
                                continue;
                            }
                    
                            // Codificar o conteúdo em Base64
                            $contentBase64 = base64_encode($contentBytes);
                            if ($contentBase64 === false) {
                                error_log("Falha ao codificar o conteúdo do anexo em Base64: $filename");
                                continue;
                            }
                    
                            // Salva o anexo no banco de dados
                            $this->emailModel->saveAttachment(
                                $emailId,
                                $filename,
                                $fullMimeType,
                                strlen($contentBase64), // Armazena o tamanho da string Base64
                                $contentBase64 // Salva o conteúdo codificado em Base64
                            );
                        }
                    }

                    $event = [
                        'type' => 'email_received',
                        'email_id' => $messageId,
                        'subject' => $subject,
                        'from' => $fromAddress,
                        'to' => array_map(fn(EmailAddress $addr) => $addr->getAddress(), iterator_to_array($message->getTo())),
                        'received_at' => $date_received,
                        'user_id' => $user_id,
                        'folder' => $mailbox->getName(),
                        'uuid' => uniqid(),
                    ];

                    $this->webhookService->triggerEvent($event, $user_id);

                    $uidCounter++;

                    if ($date_received) {
                        $lastEmailDate = $date_received;
                    }
                }

                if ($lastEmailDate) {
                    $this->emailModel->updateLastEmailSyncDateByFolder($user_id, $mailbox->getName(), $lastEmailDate);
                }

                error_log("Sincronização de e-mails concluída para a pasta " . $mailbox->getName());
            }
        } catch (Exception $e) {
            error_log("Erro durante a sincronização de e-mails: " . $e->getMessage());
        }

        error_log("Sincronização de e-mails concluída para o usuário $user_id e provedor $provider_id");
    }
}