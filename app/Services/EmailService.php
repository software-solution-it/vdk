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
use App\Controllers\ErrorLogController;
use PDO;

class EmailService {
    private $emailAccountModel;
    private $userService;
    private $userModel;
    private $emailModel;
    private $db;
    private $webhookService;
    private $rabbitMQService;
    private $masterHostModel;
    private $errorLogController;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($this->db);
        $this->userService = new UserService($this->userModel);
        $this->emailAccountModel = new EmailAccount($this->db);
        $this->emailModel = new Email($this->db);
        $this->webhookService = new WebhookService();
        $this->rabbitMQService = new RabbitMQService($this->db);
        $this->masterHostModel = new MasterHost($this->db);
        $this->errorLogController = new ErrorLogController();
    }

    public function sendEmail($user_id, $email_account_id, $recipientEmails, $subject, $htmlBody, $plainBody = '', $priority = null, $attachments = [], $ccEmails = [], $bccEmails = []) {
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
        
        $smtpConfig = $this->emailAccountModel->getById($email_account_id);
        if (!$smtpConfig) {
            return [
                'success' => false,
                'message' => 'Configurações de SMTP não encontradas para o email_account_id ' . $email_account_id
            ];
        }
        $smtpConfig['password'] = EncryptionHelper::decrypt($smtpConfig['password']);

        $message = [
            'user_id' => $user_id,
            'email_account_id' => $email_account_id, 
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
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
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
        $email_account_id = $message['email_account_id']; 
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
    
        $smtpConfig = $this->emailAccountModel->getById($email_account_id);
        if (!$smtpConfig) {
            error_log("Configurações de SMTP não encontradas para o email_account_id $email_account_id");
            return false;
        }
        $smtpConfig['password'] = EncryptionHelper::decrypt($smtpConfig['password']);
    
        $this->errorLogController->logError("SMTP Config: " . json_encode($smtpConfig), __FILE__, __LINE__);

        $mail = new PHPMailer(true);
    
        try {
            $mail->CharSet = 'UTF-8';
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
            $mail->addCustomHeader('Content-Type', 'text/html; charset=UTF-8');
    
            if ($mail->send()) {
                $event = [
                    'Status' => 'Success',
                    'Message' => 'Email sent successfully', 
                    'Data' => [ 
                        'subject' => $subject,
                        'from' => $smtpConfig['email'],
                        'to' => $recipientEmails,
                        'cc' => $ccEmails,
                        'bcc' => $bccEmails,
                        'sent_at' => date('Y-m-d H:i:s'),
                        'user_id' => $user_id
                    ]
                ];
    
                $this->webhookService->triggerEvent($event, $user_id);
    
                return true;
            } else {
                error_log("Falha ao enviar o e-mail.");
                return false;
            }
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            error_log("Erro ao enviar e-mail: " . $e->getMessage());
            return false;
        }
    }

    public function listEmails($email_id) {
        $query = "SELECT e.* 
                  FROM emails e 
                  WHERE e.id = :email_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->execute();
        $email = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$email) {
            return []; 
        }
    
        $firstEmailId = $email['id'];
        $firstEmailReference = $email['references'] ?? null;
        $firstEmailInReplyTo = $email['in_reply_to'] ?? null;
    
        $threadEmails = [];
    
        if (empty($firstEmailReference) && empty($firstEmailInReplyTo)) {
            $threadEmails[] = $email;
    
            $query = "SELECT e.* 
                      FROM emails e 
                      WHERE e.in_reply_to = :in_reply_to 
                      ORDER BY e.date_received ASC"; 
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':in_reply_to', $firstEmailId);
            $stmt->execute();
            $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $threadEmails = array_merge($threadEmails, $replies); 
        } else {
            $query = "SELECT e.* 
                      FROM emails e 
                      WHERE e.references = :references 
                      ORDER BY e.date_received ASC"; 
    
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':references', $firstEmailReference);
            $stmt->execute();
            $threadEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            array_unshift($threadEmails, $email); 
        }
    
        foreach ($threadEmails as &$threadEmail) {
            if (!empty($threadEmail['content'])) {
                $threadEmail['content'] = base64_encode($threadEmail['content']);
            }
        }
    
        return $threadEmails; 
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

    public function getEmailThread($conversation_Id) {
        $query = "SELECT * FROM emails WHERE conversation_Id = :conversation_Id OR in_reply_to = :conversation_Id ORDER BY date_received ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':conversation_Id', $conversation_Id);
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
            $mail->CharSet = 'UTF-8';
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
            $mail->addCustomHeader('Content-Type', 'text/html; charset=UTF-8');

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
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            error_log("Erro ao enviar e-mail: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail: ' . $e->getMessage()
            ];
        }
    }
}
