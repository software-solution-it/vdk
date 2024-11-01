<?php

namespace App\Services;

use App\Models\Email;
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
    private $emailModel;
    private $db;
    private $webhookService;
    private $rabbitMQService;
    private $masterHostModel; // Adicionado

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($this->db);
        $this->userService = new UserService($this->userModel);
        $this->emailAccountModel = new EmailAccount($this->db);
        $this->emailModel = new Email($this->db);
        $this->webhookService = new WebhookService();
        $this->rabbitMQService = new RabbitMQService($this->db);
        $this->masterHostModel = new MasterHost($this->db); // Adicionado
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
                $mail->Priority = max(1, min(99, $priority)); 
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
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($emails as &$email) {
            if (!empty($email['content'])) {
                $email['content'] = base64_encode($email['content']);
            }
        }
    
        return $emails;
    }

    public function checkEmailRecords($domain) {
        $dkim = $this->emailModel->checkDkim($domain);
        $dmarc = $this->emailModel->checkDmarc($domain);
        $spf = $this->emailModel->checkSpf($domain);
    
        return [
            'dkim' => is_array($dkim) || is_string($dkim) ? $dkim : [],
            'dmarc' => is_array($dmarc) || is_string($dmarc) ? $dmarc : [],
            'spf' => is_array($spf) || is_string($spf) ? $spf : [],
        ];
    }

    public function viewEmail($email_id) {
        $query = "SELECT * FROM emails WHERE id = :email_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

  
    public function getEmailThread($message_id) {
        $query = "SELECT * FROM emails WHERE message_id = :message_id OR in_reply_to = :message_id ORDER BY date_received ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        return $this->sendEmailWithMasterHost($email, $subject, $htmlBody, $plainBody);
    }


    public function sendEmailWithMasterHost($recipientEmail, $subject, $htmlBody, $plainBody = '') {
        $smtpConfig = $this->masterHostModel->getMasterHost();

        if (!$smtpConfig) {
            error_log("Configurações de SMTP não encontradas para o MasterHost");
            return [
                'success' => false,
                'message' => 'Configurações de SMTP não encontradas para o MasterHost'
            ];
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
            $mail->addAddress($recipientEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

            if ($mail->send()) {
                return [
                    'success' => true,
                    'message' => 'E-mail enviado com sucesso.'
                ];
            } else {
                error_log("Falha ao enviar o e-mail.");
                return [
                    'success' => false,
                    'message' => 'Falha ao enviar o e-mail.'
                ];
            }
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail: ' . $e->getMessage()
            ];
        }
    }
}
