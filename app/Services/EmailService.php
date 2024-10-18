<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\MasterHost;
use App\Helpers\EncryptionHelper;
use App\Services\RabbitMQService;
use App\Services\UserService;
use App\Services\WebhookService;
use PDO;

class EmailService {
    private $emailAccountModel;
    private $userService;
    private $userModel;
    private $db;
    private $webhookService;
    private $rabbitMQService;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($this->db);
        $this->userService = new UserService($this->userModel);
        $this->emailAccountModel = new EmailAccount($this->db);
        $this->webhookService = new WebhookService($this->db);
        $this->rabbitMQService = new RabbitMQService($this->db);
    }

    public function sendEmail($user_id, $recipientEmails, $subject, $htmlBody, $plainBody = '', $priority = null, $attachments = [], $ccEmails = [], $bccEmails = []) {
        if (!is_array($recipientEmails)) {
            $recipientEmails = [$recipientEmails];
        }
    
        if (!is_array($ccEmails)) {
            $ccEmails = [$ccEmails];
        }
        if (!is_array($bccEmails)) {
            $bccEmails = [$bccEmails];
        }
    
        $user = $this->userService->getUserById($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuário não encontrado'
            ];
        }
    
        $message = [
            'user_id' => $user_id,
            'recipientEmails' => $recipientEmails,
            'ccEmails' => $ccEmails,
            'bccEmails' => $bccEmails,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'plainBody' => $plainBody,
            'priority' => $priority,
            'attachments' => $attachments,
        ];
    
        $queue_name = 'email_sending_queue';
        try {
            $this->rabbitMQService->publishMessage($queue_name, $message, $user_id);
        } catch (Exception $e) {
            error_log("Erro ao enfileirar e-mail: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enfileirar e-mail: ' . $e->getMessage()
            ];
        }
    
        $this->processEmailSending($message);
    
        return [
            'success' => true,
            'message' => 'E-mail enviado com sucesso.'
        ];
    }
    public function processEmailSending($message) {
        $user_id = $message['user_id'];
        $recipientEmails = $message['recipientEmails'];
        $ccEmails = $message['ccEmails'] ?? [];
        $bccEmails = $message['bccEmails'] ?? [];
        $subject = $message['subject'];
        $htmlBody = $message['htmlBody'];
        $plainBody = $message['plainBody'] ?? '';
        $priority = $message['priority'] ?? null;
        $attachments = $message['attachments'] ?? [];
    
        $user = $this->userService->getUserById($user_id);
        if (!$user) {
            error_log("Usuário não encontrado: $user_id");
            return false;
        }
    
        $smtpConfig = $this->emailAccountModel->getByUserId($user_id);
        if (!$smtpConfig) {
            error_log("Configurações de SMTP não encontradas para o usuário $user_id");
            return false;
        }
        $smtpConfig['password'] = EncryptionHelper::decrypt($smtpConfig['password']);
    
        $mail = new PHPMailer(true);
    
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpConfig['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpConfig['email'];
            $mail->Password   = $smtpConfig['password'];
            $mail->SMTPSecure = $smtpConfig['encryption'];
            $mail->Port       = $smtpConfig['smtp_port'];
    
            $mail->setFrom($smtpConfig['email'], $smtpConfig['name']);
    
            foreach ($recipientEmails as $recipientEmail) {
                $mail->addAddress($recipientEmail);
            }
    
            foreach ($ccEmails as $ccEmail) {
                $mail->addCC($ccEmail);
            }
    
            foreach ($bccEmails as $bccEmail) {
                $mail->addBCC($bccEmail);
            }
    
            if ($priority !== null) {
                $mail->Priority = max(1, min(99, $priority)); // Limita a prioridade entre 1 e 99
            }
    
            foreach ($attachments as $attachment) {
                if (isset($attachment['tmp_name']) && is_file($attachment['tmp_name'])) {
                    $mail->addAttachment($attachment['tmp_name'], $attachment['name']);
                } elseif (isset($attachment['content'], $attachment['name'], $attachment['type'])) {
                    $mail->addStringAttachment($attachment['content'], $attachment['name'], 'base64', $attachment['type']);
                } else {
                    error_log("Anexo inválido ou incompleto: " . json_encode($attachment));
                }
            }
    
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);
    
            if ($mail->send()) {
                $event = [
                    'type' => 'email_sent',
                    'subject' => $subject,
                    'from' => $smtpConfig['email'],
                    'to' => $recipientEmails,
                    'cc' => $ccEmails,
                    'bcc' => $bccEmails,
                    'sent_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user_id
                ];
    
                $this->webhookService->triggerEvent($event, $user_id);
    
                return true;
            } else {
                error_log("Falha ao enviar o e-mail.");
                return false;
            }
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail: " . $e->getMessage());
            return false;
        }
    }

    public function listEmails($user_id, $folder = '*', $search = '', $startDate = '', $endDate = '') {
        if ($folder == '*') {
            $query = "SELECT e.*, a.filename, a.mime_type, a.size, a.content 
                      FROM emails e 
                      LEFT JOIN email_attachments a ON e.id = a.email_id 
                      WHERE e.user_id = :user_id";
        } else {
            $query = "SELECT e.*, a.filename, a.mime_type, a.size, a.content
                      FROM emails e 
                      LEFT JOIN email_attachments a ON e.id = a.email_id 
                      WHERE e.user_id = :user_id AND e.folder LIKE :folder";
        }
    
        if (!empty($search)) {
            $query .= " AND (e.subject LIKE :search OR e.sender LIKE :search OR e.recipient LIKE :search OR e.email_id LIKE :search OR e.`references` LIKE :search OR e.in_reply_to LIKE :search)";
        }
    
       if (!empty($startDate) && !empty($endDate)) {
            $query .= " AND e.date_received BETWEEN :startDate AND :endDate";
        }
    
        $query .= " ORDER BY e.`references`, e.in_reply_to, e.date_received DESC";
    
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    
        if ($folder != '*') {
            $folderTerm = "%" . $folder . "%";
            $stmt->bindParam(':folder', $folderTerm);
        }
    
        if (!empty($search)) {
            $searchTerm = "%" . $search . "%";
            $stmt->bindParam(':search', $searchTerm);
        }
    
        if (!empty($startDate) && !empty($endDate)) {
            $stmt->bindParam(':startDate', $startDate);
            $stmt->bindParam(':endDate', $endDate);
        }
    
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function viewEmail($email_id) {
        $query = "SELECT * FROM emails WHERE id = :email_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markEmailAsSpam($userAccount, $provider_id, $email_id): bool {
        try {
            $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($userAccount, $provider_id);

            if (empty($emailAccount['imap_host']) || empty($emailAccount['email']) || empty($emailAccount['password'])) {
                throw new Exception('Detalhes da conta de email inválidos ou incompletos.');
            }

            $imapPath = '{' . $emailAccount['imap_host'] . ':' . $emailAccount['imap_port'] . '/imap/ssl}';
            $imapStream = imap_open($imapPath, $emailAccount['email'], EncryptionHelper::decrypt($emailAccount['password']));

            if (!$imapStream) {
                throw new Exception('Falha ao conectar ao servidor IMAP: ' . imap_last_error());
            }

            $spamFolder = '[Gmail]/Spam';

            try {
                $result = imap_mail_move($imapStream, 4, $spamFolder); // Usa o UID diretamente

                if (!$result) {
                    throw new Exception('Falha ao mover o email. Verifique se o UID é válido e se a pasta de destino é acessível: ' . imap_last_error());
                }

                imap_expunge($imapStream);

                $this->updateFolderInDatabase($email_id, $spamFolder);

                imap_close($imapStream);

                return true;
            } catch (Exception $e) {
                imap_close($imapStream);
                throw new Exception('Erro ao mover o email para ' . $spamFolder . ': ' . $e->getMessage());
            }

        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function deleteSpamEmail($emailAccount, $email_id) {
        $mailbox = $this->getMailbox($emailAccount);
        $mailbox->deleteMail($email_id);
        return $this->deleteEmailFromDatabase($email_id);
    }

    public function unmarkSpam($emailAccount, $email_id, $destinationFolder = 'INBOX') {
        $mailbox = $this->getMailbox($emailAccount);
        $mailbox->moveMail($email_id, $destinationFolder);
        return $this->updateFolderInDatabase($email_id, $destinationFolder);
    }

    public function getEmailThread($message_id) {
        $query = "SELECT * FROM emails WHERE message_id = :message_id OR in_reply_to = :message_id ORDER BY date_received ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMailbox($emailAccount) {
        return new Mailbox(
            '{' . $emailAccount['imap_host'] . ':' . $emailAccount['imap_port'] . '/imap/ssl}',
            $emailAccount['email'],
            EncryptionHelper::decrypt($emailAccount['password']),
            __DIR__,
            'UTF-8'
        );
    }

    private function updateFolderInDatabase($email_id, $folder) {
        $query = "UPDATE emails SET folder = :folder WHERE `uid` = :email_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':folder', $folder);
        $stmt->bindParam(':email_id', $email_id);
        return $stmt->execute();
    }

    private function deleteEmailFromDatabase($email_id) {
        $query = "DELETE FROM emails WHERE id = :email_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        return $stmt->execute();
    }

    

    public function sendVerificationEmail($user_id, $email, $code) {
        $subject = 'Seu código de verificação';
        $htmlBody = 'Seu código de verificação é: <b>' . $code . '</b>';
        $plainBody = null;

        return $this->sendEmail($user_id, $email, $subject, $htmlBody, $plainBody, true);
    }
}
