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
use App\Controllers\ErrorLogController; // Inclui o controlador de logs de erro
use Exception;

class EmailSyncService
{
    private $emailModel;
    private $emailAccountModel;
    private $rabbitMQService;
    private $webhookService;
    private $errorLogController; // Adiciona a variável do controlador de logs
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
        $this->rabbitMQService = new RabbitMQService($db);
        $this->webhookService = new WebhookService();
        $this->errorLogController = new ErrorLogController(); // Inicializa o controlador de logs de erro
    }

    

    public function startConsumer($user_id, $provider_id)
    {
        $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);
    }

    public function reconnectRabbitMQ() {
        if (!$this->db || !$this->db->isConnected()) {
            $this->rabbitMQService->connect();
        }
    }

    public function updateTokens($emailAccountId, $access_token, $refresh_token = null)
{
    try {
        // Atualiza os tokens no modelo EmailAccount
        $this->emailAccountModel->updateTokens(
            $emailAccountId,
            $access_token,
            $refresh_token
        );

        // Loga o sucesso da atualização
        error_log("Access token e refresh token atualizados com sucesso para a conta de e-mail ID: $emailAccountId");

    } catch (Exception $e) {
        // Caso ocorra um erro, registra o erro e utiliza o ErrorLogController para logá-lo
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
                $this->syncEmails(
                    $task['user_id'],
                    $task['provider_id'],
                    $task['email'],
                    $task['imap_host'],
                    $task['imap_port'],
                    $task['password'],
                    $task['oauth2_token'] ?? null // Adiciona suporte para OAuth2
                );

                $msg->ack();
                error_log("Sincronização concluída para a mensagem na fila.");

                if ($this->rabbitMQService->markJobAsExecuted($queue_name)) {
                    error_log("Job marcado como executado com sucesso.");
                } else {
                    error_log("Erro ao marcar o job como executado.");
                }

                $this->syncEmailsByUserIdAndProviderId($user_id, $provider_id);

            } catch (Exception $e) {
                error_log("Erro ao sincronizar e-mails: " . $e->getMessage());
                // Loga o erro usando o controlador de logs
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
            // Loga o erro usando o controlador de logs
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
            // Aqui você pode logar o erro ou tratar como preferir
            error_log("Erro ao buscar conta de e-mail: " . $e->getMessage());
            throw $e;
        }
    }

    public function syncEmailsByUserIdAndProviderId($user_id, $provider_id)
{
    set_time_limit(0);

    $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);

    if (!$emailAccount) {
        error_log("Conta de e-mail não encontrada para user_id={$user_id} e provider_id={$provider_id}");
        $this->errorLogController->logError("Conta de e-mail não encontrada para user_id={$user_id} e provider_id={$provider_id}", __FILE__, __LINE__, $user_id);
        return;
    }

    if (!empty($emailAccount['client_id']) && !empty($emailAccount['client_secret'])) {
        if (empty($emailAccount['oauth_token'])) {
            $this->requestNewOAuthToken($emailAccount);
        } else {
            $this->refreshOAuthTokenIfNeeded($emailAccount);
        }
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
            'password' => EncryptionHelper::decrypt($emailAccount['password']),
            'oauth2_token' => $emailAccount['oauth_token'] ?? null, // Inclui o token OAuth2
            'tenant_id' => $emailAccount['tenant_id'] // Inclui o tenant_id
        ];

        $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);
        $this->consumeEmailSyncQueue($user_id, $provider_id, $queue_name);

    } catch (Exception $e) {
        error_log("Erro ao adicionar tarefa de sincronização no RabbitMQ: " . $e->getMessage());
        $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
        throw $e;
    }
}
private function requestNewOAuthToken($emailAccount, $authCode = null)
{
    // URL para solicitar o token
    $token_url = "https://login.microsoftonline.com/{$emailAccount['tenant_id']}/oauth2/v2.0/token";

    // Verifica se há um código de autorização ou se vai usar o refresh_token
    if ($authCode) {
        $params = [
            'client_id' => $emailAccount['client_id'],
            'client_secret' => $emailAccount['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => 'YOUR_REDIRECT_URI', // Altere para a URI de redirecionamento correta
            'scope' => 'https://outlook.office365.com/IMAP.AccessAsUser.All offline_access'
        ];
    } else {
        // Usa o refresh_token para obter um novo token de acesso
        $params = [
            'client_id' => $emailAccount['client_id'],
            'client_secret' => $emailAccount['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $emailAccount['refresh_token'],
            'scope' => 'https://outlook.office365.com/IMAP.AccessAsUser.All offline_access'
        ];
    }

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        // Atualiza os tokens (access e refresh)
        $this->emailAccountModel->updateTokens(
            $emailAccount['id'],
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? $emailAccount['refresh_token']
        );
        error_log("Novo token OAuth2 gerado e salvo.");
    } else {
        error_log("Erro ao solicitar um novo token OAuth2: " . json_encode($tokenData));
        // Loga o erro usando o controlador de logs
        $this->errorLogController->logError("Erro ao solicitar um novo token OAuth2: " . json_encode($tokenData), __FILE__, __LINE__, $emailAccount['user_id']);
    }
}


    private function refreshOAuthTokenIfNeeded($emailAccount)
    {
        $token_expiry_threshold = 3600; // em segundos

        if (time() > strtotime($emailAccount['updated_at']) + $token_expiry_threshold) {
            error_log("Token OAuth2 expirado, tentando renovar...");
            $this->refreshOAuthToken($emailAccount);
        }
    }

    private function refreshOAuthToken($emailAccount)
    {
        // Use o tenant_id na URL
        $token_url = "https://login.microsoftonline.com/{$emailAccount['tenant_id']}/oauth2/v2.0/token";
    
        $params = [
            'client_id' => $emailAccount['client_id'],
            'client_secret' => $emailAccount['client_secret'],
            'refresh_token' => $emailAccount['refresh_token'],
            'grant_type' => 'refresh_token'
        ];
    
        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $tokenData = json_decode($response, true);
    
        if (isset($tokenData['access_token'])) {
            // Atualiza os tokens na base de dados
            $this->emailAccountModel->updateTokens(
                $emailAccount['id'],
                $tokenData['access_token'],
                $tokenData['refresh_token'] ?? $emailAccount['refresh_token']
            );
            error_log("Token OAuth2 renovado com sucesso.");
        } else {
            error_log("Erro ao renovar o token OAuth2: " . json_encode($tokenData));
            // Loga o erro usando o controlador de logs
            $this->errorLogController->logError("Erro ao renovar o token OAuth2: " . json_encode($tokenData), __FILE__, __LINE__, $emailAccount['user_id']);
            return;
        }
    }

    private function generateQueueName($user_id, $provider_id)
    {
        return 'email_sync_queue_' . $user_id . '_' . $provider_id . '_' . time();
    }

    private function syncEmails($user_id, $provider_id, $email, $imap_host, $imap_port, $password, $oauth2_token)
    {
        error_log("Sincronizando e-mails para o usuário $user_id e provedor $provider_id");

        $lastSyncDate = $this->emailModel->getLastEmailSyncDate($user_id);
        $lastSyncDateFormatted = $lastSyncDate ? new \DateTime($lastSyncDate) : null;

        try {
            $server = new Server($imap_host, $imap_port);
            if ($oauth2_token) {
                $connection = $server->authenticate($email, $oauth2_token); 
            } else {
                $connection = $server->authenticate($email, $password);
            }

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
            // Loga o erro usando o controlador de logs
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__, $user_id);
            throw $e;
        }

        error_log("Sincronização de e-mails concluída para o usuário $user_id e provedor $provider_id");
    }
}
