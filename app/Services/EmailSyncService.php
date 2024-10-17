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

    public function startConsumerAndSync($user_id, $provider_id)
    {
        $this->startConsumer($user_id, $provider_id);

        $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);
    }
    public function startConsumer($user_id, $provider_id)
    {
        $consumer_id = "email_consumer_{$user_id}_{$provider_id}";
        $config_directory = '/app/scripts/supervisor_configs/';
        $log_directory = '/var/log/supervisor/';
        $directory_permissions = 0755;
    
        if (!file_exists($config_directory)) {
            error_log("Diretório de configuração não existe. Tentando criar: {$config_directory}");
            if (!mkdir($config_directory, $directory_permissions, true)) {
                error_log("Falha ao criar o diretório de configuração: {$config_directory}");
                return;
            }
            error_log("Diretório de configuração criado: {$config_directory}");
        } else {
            error_log("Diretório de configuração encontrado: {$config_directory}");
        }
    
        if (!file_exists($log_directory)) {
            error_log("Diretório de logs não existe. Tentando criar: {$log_directory}");
            if (!mkdir($log_directory, $directory_permissions, true)) {
                error_log("Falha ao criar o diretório de logs: {$log_directory}");
                return;
            }
            error_log("Diretório de logs criado: {$log_directory}");
        } else {
            error_log("Diretório de logs encontrado: {$log_directory}");
        }
    
        $config_file = $config_directory . "{$consumer_id}.conf";
    
        if (file_exists($config_file)) {
            error_log("Arquivo de configuração já existe: {$config_file}. Pulando criação.");
        } else {
            $config_content = "
                [program:{$consumer_id}]
                command=/usr/bin/php /home/suporte/vdk/app/Scripts/email_consumer.php {$user_id} {$provider_id}
                autostart=true
                autorestart=true
                stdout_logfile={$log_directory}{$consumer_id}.log
                stderr_logfile={$log_directory}{$consumer_id}_err.log
                ";
    
            if (file_put_contents($config_file, $config_content) === false) {
                error_log("Falha ao escrever o arquivo de configuração: {$config_file}");
                return;
            }
    
            error_log("Arquivo de configuração criado: {$config_file}");
        }
    
        // Executar os comandos e garantir que shell_exec não retorne null
        $reread_output = shell_exec('supervisorctl reread 2>&1') ?? '';
        $update_output = shell_exec('supervisorctl update 2>&1') ?? '';
    
        // Verificar o resultado de reread_output e update_output
        if (strpos($reread_output, 'ERROR') !== false) {
            error_log("Erro ao executar 'supervisorctl reread': {$reread_output}");
        } else {
            error_log("'supervisorctl reread' executado com sucesso.");
        }
    
        if (strpos($update_output, 'ERROR') !== false) {
            error_log("Erro ao executar 'supervisorctl update': {$update_output}");
        } else {
            error_log("'supervisorctl update' executado com sucesso.");
        }
    
        $status = shell_exec("supervisorctl status {$consumer_id} 2>&1") ?? '';
        error_log("Status do consumidor {$consumer_id}: {$status}");
    
        error_log("Novo consumidor {$consumer_id} criado para user_id={$user_id} e provider_id={$provider_id}.");
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

    public function generateQueueName($user_id, $provider_id)
    {
        return 'email_sync_queue_' . $user_id . '_' . $provider_id . '_' . time();
    }

    public function consumeEmailSyncQueue($user_id, $provider_id, $queue_name)
    {
        error_log("Iniciando o consumidor RabbitMQ para sincronização de e-mails...");

        error_log("Consumindo a fila: " . $queue_name);

        $callback = function ($msg) use ($user_id, $provider_id, $queue_name) {
            error_log("Recebida tarefa de sincronização: " . $msg->body);
            $task = json_decode($msg->body, true);

            if (!$task) {
                error_log("Erro ao decodificar a mensagem da fila.");
                $msg->nack(false, true);
                return;
            }

            error_log("Sincronizando e-mails com as seguintes informações: " . json_encode($task));

            try {
                $this->syncEmails(
                    $task['user_id'],
                    $task['provider_id'],
                    $task['email'],
                    $task['imap_host'],
                    $task['imap_port'],
                    $task['password']
                );

                $msg->ack();

                $this->rabbitMQService->markJobAsExecuted($queue_name);

                $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);

            } catch (Exception $e) {
                error_log("Erro ao sincronizar e-mails: " . $e->getMessage());
                $msg->nack(false, true);
            }
        };

        try {
            $this->rabbitMQService->consumeQueue($queue_name, $callback);
        } catch (Exception $e) {
            error_log("Erro ao consumir a fila RabbitMQ: " . $e->getMessage());
        }
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

                    if ($message->hasAttachments()) {
                        $attachments = $message->getAttachments();

                        foreach ($attachments as $attachment) {
                            $filename = $attachment->getFilename();
                            $mimeType = $attachment->getType();
                            $size = $attachment->getBytes();
                            $content = $attachment->getDecodedContent();

                            $this->emailModel->saveAttachment(
                                $messageId,
                                $filename,
                                $mimeType,
                                $size,
                                $content
                            );
                        }
                    }

                    $ccAddresses = $message->getCc();
                    $cc = $ccAddresses ? implode(', ', array_map(fn(EmailAddress $addr) => $addr->getAddress(), $ccAddresses)) : null;

                    $this->emailModel->saveEmail(
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
