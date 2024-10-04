<?php

require_once __DIR__ . '/../models/Email.php';
require_once __DIR__ . '/../models/EmailAccount.php';
require_once __DIR__ . '/../helpers/EncryptionHelper.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../services/RabbitMQService.php';

use PhpImap\Mailbox;

class EmailSyncService {
    private $emailModel;
    private $emailAccountModel;
    private $rabbitMQService;

    public function __construct($db) {
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
        $this->rabbitMQService = new RabbitMQService($db);
    }

    public function syncEmailsByUserIdAndProviderId($user_id, $provider_id) {
        set_time_limit(0);
    
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        
        if (!$emailAccount) {
            return ['status' => false, 'message' => 'Email account not found for the given user and provider'];
        }
    
        $queue_name = 'email_sync_queue_' . $user_id . '_' . $provider_id;
    
        // Remover a fila existente antes de adicionar uma nova tarefa de sincronização
        $this->rabbitMQService->clearQueue($queue_name);
    
        error_log("Conta de e-mail encontrada: " . $emailAccount['email']);
    
        try {
            $message = [
                'user_id' => $user_id,
                'provider_id' => $provider_id,
                'email' => $emailAccount['email'],
                'imap_host' => $emailAccount['imap_host'],
                'imap_port' => $emailAccount['imap_port'],
                'password' => EncryptionHelper::decrypt($emailAccount['password'])
            ];
    
            // Publicar nova mensagem de sincronização na fila
            $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);
    
            return ['status' => true, 'message' => 'Email synchronization task added to queue'];
        } catch (Exception $e) {
            error_log("Erro ao adicionar tarefa de sincronização no RabbitMQ: " . $e->getMessage());
            return ['status' => false, 'message' => 'Error adding synchronization task: ' . $e->getMessage()];
        }
    }

    public function consumeEmailSyncQueue($user_id, $provider_id) {
        error_log("Iniciando o consumidor RabbitMQ para sincronização de e-mails...");

        $queue_name = 'email_sync_queue_' . $user_id . '_' . $provider_id;

        $callback = function ($msg) {
            error_log("Recebida tarefa de sincronização: " . $msg->body);
            $task = json_decode($msg->body, true);

            $this->syncEmails(
                $task['user_id'],
                $task['provider_id'],
                $task['email'],
                $task['imap_host'],
                $task['imap_port'],
                $task['password']
            );
        };

        $this->rabbitMQService->consumeQueue($queue_name, $callback);
    }

    private function syncEmails($user_id, $provider_id, $email, $imap_host, $imap_port, $password) {
        error_log("Sincronizando e-mails para o usuário $user_id e provedor $provider_id");

        // Obter todas as pastas do Gmail
        $imapPath = '{' . $imap_host . ':' . $imap_port . '/imap/ssl}';
        try {
            $imapStream = imap_open($imapPath, $email, $password);
            $folders = imap_list($imapStream, $imapPath, '*');
            if (!$folders) {
                error_log("Nenhuma pasta encontrada para a conta $email");
                return;
            }

            $folderNames = [];
            foreach ($folders as $folder) {
                $folderNames[] = str_replace($imapPath, '', $folder);
            }
            imap_close($imapStream);
        } catch (Exception $e) {
            error_log("Erro ao obter pastas de e-mail: " . $e->getMessage());
            return;
        }

        // Sincronizar e-mails de todas as pastas
        foreach ($folderNames as $folder) {
            $mailbox = new Mailbox(
                $imapPath . $folder,
                $email,
                $password,
                __DIR__,
                'UTF-8'
            );

            try {
                $criteria = 'ALL'; // Pode ser modificado conforme necessidade, como "SEEN", "UNSEEN", etc.
                $mailsIds = $mailbox->searchMailbox($criteria);
                if (!$mailsIds) {
                    error_log("Nenhum e-mail encontrado na pasta $folder para a conta $email");
                    continue;
                }

                error_log("E-mails encontrados na pasta $folder: " . count($mailsIds));

                foreach ($mailsIds as $mailId) {
                    $mail = $mailbox->getMail($mailId);

                    if ($this->emailModel->emailExistsByMessageId($mail->messageId)) {
                        error_log("E-mail com Message-ID " . $mail->messageId . " já existe, ignorando.");
                        continue;
                    }

                    $date_received = isset($mail->date) ? date('Y-m-d H:i:s', strtotime($mail->date)) : null;

                    $overview = $mailbox->getMailsInfo([$mailId])[0];
                    $isRead = ($overview->seen) ? 1 : 0;
                    $isDeleted = ($overview->deleted) ? 1 : 0;

                    error_log("Sincronizando e-mail com Message-ID " . $mail->messageId);
                    error_log("Assunto: " . ($mail->subject ?? '(no subject)'));
                    error_log("Remetente: " . $mail->fromAddress);
                    error_log("Destinatários: " . $mail->toString);

                    $this->emailModel->saveEmail(
                        $user_id,
                        $mail->messageId,
                        $mail->subject ?? '(no subject)',
                        $mail->fromAddress,
                        $mail->toString,
                        $mail->textHtml ?? $mail->textPlain,
                        $date_received,
                        $mail->messageId,
                        $mail->references ?? '',
                        $mail->inReplyTo ?? '',
                        $isRead,
                        $isDeleted,
                        $folder  // Armazena a pasta onde o e-mail foi encontrado
                    );
                }

                error_log("Sincronização de e-mails concluída para a pasta $folder");
            } catch (Exception $e) {
                error_log("Erro durante a sincronização de e-mails na pasta $folder: " . $e->getMessage());
            }
        }

        error_log("Sincronização de e-mails concluída para o usuário $user_id e provedor $provider_id");
    }
}
