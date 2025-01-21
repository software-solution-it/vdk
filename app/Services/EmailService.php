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
use Ddeboer\Imap\Server;
use App\Services\WebhookService;
use Ddeboer\Imap\Search\Text\Text;
use Ddeboer\Imap\Message; 
use App\Controllers\ErrorLogController;
use App\Models\EmailAttachment;  
    
           
use PDO;
use App\Services\S3Service;

class EmailService {
    private $emailAccountModel;
    private $userService;
    private $userModel;
    private $emailModel;
    private $emailAttachmentModel; 
    private $db;
    private $webhookService;
    private $rabbitMQService;
    private $masterHostModel;
    private $errorLogController;
    private $s3Service;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($this->db);
        $this->userService = new UserService($this->userModel);
        $this->emailAccountModel = new EmailAccount($this->db);
        $this->emailModel = new Email($this->db);
        $this->webhookService = new WebhookService();
        $this->rabbitMQService = new RabbitMQService($this->db);
        $this->masterHostModel = new MasterHost($this->db);
        $this->emailAttachmentModel = new EmailAttachment($db);
        $this->errorLogController = new ErrorLogController();
        $this->s3Service = new S3Service($this->db);
    }

    public function sendEmail($user_id, $email_account_id, $recipientEmails, $subject, $htmlBody, $plainBody = '', $priority = null, $attachments = [], $ccEmails = [], $bccEmails = [], $inReplyTo = null) {
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
            'inReplyTo' => $inReplyTo 
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
    


    public function moveEmail($email_id, $new_folder_name) {
        try {
            // Obter os detalhes do e-mail
            $emailDetails = $this->emailModel->getEmailById($email_id);
            if (!$emailDetails) {
                throw new Exception("E-mail não encontrado no banco de dados.");
            }
    
            // Obter os detalhes da conta de e-mail
            $accountDetails = $this->emailAccountModel->getById($emailDetails['email_account_id']);
            if (!$accountDetails) {
                throw new Exception("Conta de e-mail associada não encontrada.");
            }
    
            // Descriptografar a senha da conta de e-mail
            $accountDetails['password'] = EncryptionHelper::decrypt($accountDetails['password']);
            
            // Obter o IMAP Host e Porta da conta
            $imap_host = $accountDetails['imap_host'];
            $imap_port = $accountDetails['imap_port'];
    
            // Conectar ao servidor IMAP
            $server = new Server($imap_host, $imap_port);
            
            // Autenticar no servidor IMAP com o e-mail e senha
            $connection = $server->authenticate($accountDetails['email'], $accountDetails['password']);
            if (!$connection) {
                throw new Exception("Falha na autenticação no servidor IMAP.");
            }
    
            // Buscar o nome da pasta original
            $originalFolderName = $emailDetails['folder_name']; // Assumindo que a pasta original é armazenada em 'folder_name'
    
            // Obter as caixas de entrada original e nova
            $originalMailbox = $connection->getMailbox($originalFolderName);
            $newMailbox = $connection->getMailbox($new_folder_name);
            if (!$originalMailbox) {
                throw new Exception("Pasta original '$originalFolderName' não encontrada.");
            }
            if (!$newMailbox) {
                throw new Exception("Pasta de destino '$new_folder_name' não encontrada.");
            }
    
            // Obter a ordem do e-mail
            $order = $emailDetails['order'];  // 'order' define a posição do e-mail pela data de recebimento
            
            // Buscar todos os e-mails da pasta, ordenados pela data de recebimento
            $emailsInFolder = $originalMailbox->getMessages();
    
            // Encontrar o e-mail correspondente à ordem
            $emailToMove = null;
            $counter = 1;
            foreach ($emailsInFolder as $message) {
                if ($message->getId() === $emailDetails['email_id']) {
                    $emailToMove = $message;
                    break;
                }
                $counter++;
            }
    
            if (!$emailToMove) {
                throw new Exception("E-mail de ordem '$order' não encontrado na pasta '$originalFolderName'.");
            }
    
            // Mover a mensagem para a nova pasta
            $emailToMove->move($newMailbox);
    
            // Expurgar a pasta de origem
            $connection->expunge();
    
            // Deletar o e-mail da base de dados após mover
            $this->emailModel->deleteEmail($emailDetails['email_id']);
    
            // Fechar a conexão
            $connection->close();
    
            return true;
    
        } catch (Exception $e) {
            // Log de erro
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            throw new Exception("Erro ao mover o e-mail: " . $e->getMessage());
        }
    }
    
    
    


    public function deleteEmail($email_id) {
        try {
            $emailDetails = $this->emailModel->getEmailById($email_id);
            if (!$emailDetails) {
                throw new Exception("E-mail não encontrado no banco de dados.");
            }
    
            $accountDetails = $this->emailAccountModel->getById($emailDetails['email_account_id']);
            if (!$accountDetails) {
                throw new Exception("Conta de e-mail associada não encontrada.");
            }
    
                   $accountDetails['password'] = EncryptionHelper::decrypt($accountDetails['password']);
    
            $imap = imap_open(
                "{" . $accountDetails['imap_host'] . ":" . $accountDetails['imap_port'] . "/imap/ssl}INBOX",
                $accountDetails['email'],
                $accountDetails['password']
            );
    
            if (!$imap) {
                throw new Exception("Falha ao conectar ao servidor IMAP: " . imap_last_error());
            }
    
            $deleteResult = imap_delete($imap, $emailDetails['uid']);
            if (!$deleteResult) {
                throw new Exception("Erro ao excluir o e-mail no provedor: " . imap_last_error());
            }
    
            $attachments = $this->emailAttachmentModel->getAttachmentsByEmailId($email_id);
            foreach ($attachments as $attachment) {

                $this->emailAttachmentModel->deleteAttachmentsByEmailId($email_id);
            }
     
            imap_expunge($imap);
            imap_close($imap);
            $this->emailModel->deleteEmail($email_id);
    
            return true;
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            throw new Exception("Erro ao excluir o e-mail: " . $e->getMessage());
        }
    }





    public function processEmailSending($message)
{
    $user_id = $message['user_id'];
    $email_account_id = $message['email_account_id'];
    $recipientEmails = $message['recipientEmails'];
    $ccEmails = $message['ccEmails'] ?? [];
    $bccEmails = $message['bccEmails'] ?? [];
    $subject = $message['subject'];
    $htmlBody = $message['htmlBody'];
    $attachments = $message['attachments'] ?? [];
    $inReplyTo = $message['inReplyTo'] ?? null;

    $user = $this->userService->getUserById($user_id);
    if (!$user) {
        error_log("User not found: $user_id");
        return false;
    }

    $smtpConfig = $this->emailAccountModel->getById($email_account_id);
    if (!$smtpConfig) {
        error_log("SMTP configurations not found for email_account_id $email_account_id");
        return false;
    }
    $smtpConfig['password'] = EncryptionHelper::decrypt($smtpConfig['password']);

    $this->errorLogController->logError("SMTP Config: " . json_encode($smtpConfig), __FILE__, __LINE__);

    $mail = new PHPMailer(true);

    try {
        // Configuração do PHPMailer
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $smtpConfig['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['email'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = $smtpConfig['encryption'];
        $mail->Port = $smtpConfig['smtp_port'];

        $mail->setFrom($smtpConfig['email'], $user['name']);

        foreach ($recipientEmails as $recipientEmail) {
            $mail->addAddress($recipientEmail);
        }
        foreach ($ccEmails as $ccEmail) {
            $mail->addCC($ccEmail);
        }
        foreach ($bccEmails as $bccEmail) {
            $mail->addBCC($bccEmail);
        }

        foreach ($attachments as $attachment) {
            if (isset($attachment['tmp_name']) && is_file($attachment['tmp_name'])) {
                $mail->addAttachment($attachment['tmp_name'], $attachment['name']);
            } elseif (isset($attachment['content'], $attachment['name'], $attachment['type'])) {
                $mail->addStringAttachment($attachment['content'], $attachment['name'], 'base64', $attachment['type']);
            } else {
                error_log("Invalid or incomplete attachment: " . json_encode($attachment));
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        if ($inReplyTo) {
            $mail->addCustomHeader('In-Reply-To', $inReplyTo);
        }

        $mail->send();

        $event = [
            'type' => 'email_sent',
            'Status' => 'Success', 
            'Message' => 'Email sent successfully',
            'Data' => [
                'email_account_id' => $email_account_id,
                'user_id' => $user_id,
                'subject' => $subject,
                'to' => $recipientEmails,
                'cc' => $ccEmails,
                'bcc' => $bccEmails,
                'sent_at' => (new \DateTime())->format('d/m/Y H:i:s'),
                'uuid' => uniqid(),
            ]
        ];
        $this->webhookService->triggerEvent($event, $user_id);

        return true;
    } catch (Exception $e) {
        $event = [
            'type' => 'email_sending_failed',
            'Status' => 'Failed',
            'Message' => 'Failed to send email',
            'Data' => [
                'email_account_id' => $email_account_id,
                'user_id' => $user_id,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'uuid' => uniqid(),
                'failed_at'  => (new \DateTime())->format('d/m/Y H:i:s'),
            ]
        ];
        $this->webhookService->triggerEvent($event, $user_id);

        error_log('Error sending email: ' . $e->getMessage());
        return false;
    }
}
    
    
    public function listEmails($folder_id = null, $folder_name = null, $limit = 10, $offset = 0, $order = 'DESC') {
        try {
            $query = "
                SELECT 
                    e.*,
                    ef.folder_name,
                    (
                        SELECT COUNT(*) 
                        FROM email_attachments 
                        WHERE email_id = e.id
                    ) as attachment_count,
                    (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', a.id,
                                'filename', a.filename,
                                'mime_type', a.mime_type,
                                'size', a.size,
                                's3_key', a.s3_key,
                                'content_hash', a.content_hash
                            )
                        )
                        FROM email_attachments a 
                        WHERE a.email_id = e.id
                    ) as attachments
                FROM emails e
                LEFT JOIN email_folders ef ON e.folder_id = ef.id
                WHERE e.folder_id = :folder_id
                ORDER BY e.date_received " . $order . "
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Processar cada email
            foreach ($emails as &$email) {
                // Processar HTML
                if (isset($email['body_html'])) {
                    $email['body_html'] = null;
                }

                // Processar texto plano
                if (isset($email['body_text'])) {
                    $text = preg_replace('/\s+/', ' ', $email['body_text']);
                    $text = trim($text);
                    
                    if (mb_strlen($text) > 300) {
                        $text = mb_substr($text, 0, 300) . '...';
                    }
                    
                    $email['body_text'] = $text;
                }

                // Processar anexos
                if (!empty($email['attachments'])) {
                    $attachments = json_decode($email['attachments'], true);
                    if (is_array($attachments)) {
                        foreach ($attachments as &$attachment) {
                            if (!empty($attachment['s3_key'])) {
                                $attachment['presigned_url'] = $this->s3Service->generatePresignedUrl($attachment['s3_key']);
                            }
                        }
                        $email['attachments'] = $attachments;
                    } else {
                        $email['attachments'] = [];
                    }
                } else {
                    $email['attachments'] = [];
                }
            }

            // Contagem total de emails na pasta
            $countQuery = "SELECT COUNT(*) as total FROM emails WHERE folder_id = :folder_id";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'total' => (int)$total,
                'emails' => $emails
            ];

        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao listar emails: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
            throw new Exception("Erro ao listar emails: " . $e->getMessage());
        }
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

    public function viewEmailThread($email_id, $order = 'DESC') {
        try {
            $order = strtoupper($order);
            if (!in_array($order, ['ASC', 'DESC'])) {
                throw new Exception("Invalid sorting parameter. Use 'ASC' or 'DESC'.");
            }
    
            $query = "
                SELECT conversation_id
                FROM mail.emails
                WHERE id = :email_id
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
            $stmt->execute();
    
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$result || !$result['conversation_id']) {
                throw new Exception("Email does not belong to a conversation or was not found.");
            }
    
            $conversation_id = $result['conversation_id'];
    
            $query = "
                SELECT id, email_id, subject, sender, recipient, body_html, body_text, date_received, in_reply_to, `references`, folder_id, conversation_step
                FROM mail.emails
                WHERE conversation_id = :conversation_id
                ORDER BY conversation_step $order
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_STR);
            $stmt->execute();
    
            $thread = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $allAttachments = [];
    
            foreach ($thread as &$email) {
                $email_id = $email['id'];
                $query = "
                    SELECT 
                        id, 
                        mime_type,
                        filename,
                        s3_key,
                        content_hash,
                        size
                    FROM mail.email_attachments
                    WHERE email_id = :email_id
                ";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':email_id', $email_id, PDO::PARAM_INT);
                $stmt->execute();

                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Adicionar URLs pré-assinadas para anexos do S3
                foreach ($attachments as &$attachment) {
                    if (!empty($attachment['s3_key'])) {
                        try {
                            $s3Client = new \Aws\S3\S3Client([
                                'version' => 'latest',
                                'region'  => getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
                                'credentials' => [
                                    'key'    => getenv('AWS_ACCESS_KEY_ID'),
                                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                                ]
                            ]);

                            $command = $s3Client->getCommand('GetObject', [
                                'Bucket' => getenv('AWS_BUCKET') ?: 'vdkmail',
                                'Key'    => $attachment['s3_key']
                            ]);

                            $request = $s3Client->createPresignedRequest($command, '+1 hour');
                            $attachment['presigned_url'] = (string) $request->getUri();
                        } catch (Exception $e) {
                            $this->errorLogController->logError(
                                "Erro ao gerar URL pré-assinada: " . $e->getMessage(),
                                __FILE__,
                                __LINE__
                            );
                            $attachment['presigned_url'] = null;
                        }
                    }
                }

                if (!empty($attachments)) {
                    $allAttachments = array_merge($allAttachments, $attachments);
                }
            }
    
            return [
                'emails' => $thread,
                'attachments' => $allAttachments,
            ];
        } catch (Exception $e) {
            $this->errorLogController->logError("Error viewing email thread: " . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception("Error viewing email thread: " . $e->getMessage());
        }
    }
    


    public function getAttachmentById($attachment_id) {
        try {
            $query = "
                SELECT 
                    id, 
                    mime_type,
                    filename, 
                    s3_key,
                    content_hash,
                    size
                FROM mail.email_attachments
                WHERE id = :attachment_id
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':attachment_id', $attachment_id, PDO::PARAM_INT);
            $stmt->execute();

            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attachment) {
                throw new Exception("Anexo não encontrado.");
            }

            // Se tiver s3_key, gera URL pré-assinada
            if (!empty($attachment['s3_key'])) {
                try {
                    $s3Client = new \Aws\S3\S3Client([
                        'version' => 'latest',
                        'region'  => getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
                        'credentials' => [
                            'key'    => getenv('AWS_ACCESS_KEY_ID'),
                            'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                        ]
                    ]);

                    $command = $s3Client->getCommand('GetObject', [
                        'Bucket' => getenv('AWS_BUCKET') ?: 'vdkmail',
                        'Key'    => $attachment['s3_key']
                    ]);

                    $request = $s3Client->createPresignedRequest($command, '+1 hour');
                    $attachment['presigned_url'] = (string) $request->getUri();
                } catch (Exception $e) {
                    $this->errorLogController->logError(
                        "Erro ao gerar URL pré-assinada: " . $e->getMessage(),
                        __FILE__,
                        __LINE__
                    );
                    throw new Exception("Erro ao gerar URL pré-assinada: " . $e->getMessage());
                }
            }

            return $attachment;
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao buscar o anexo: " . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception("Erro ao buscar o anexo: " . $e->getMessage());
        }
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

    public function toggleFavorite($email_id, $email_account_id) {
        try {
            $result = $this->emailModel->toggleFavorite($email_id, $email_account_id);
            return $result;
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao alternar favorito do email: " . $e->getMessage(),
                __FILE__,
                __LINE__,
                $email_account_id
            );
            throw $e;
        }
    }

    public function getAttachmentsByEmailId($email_id) {
        try {
            $attachments = $this->emailAttachmentModel->getAttachmentsByEmailId($email_id);
            return $this->processAttachments($attachments);
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao buscar anexos: " . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception("Erro ao buscar anexos: " . $e->getMessage());
        }
    }

    private function processAttachments($attachments) {
        try {
            if (!$this->s3Service || !$this->s3Service->getBucketName()) {
                throw new Exception("S3 service not properly configured");
            }

            foreach ($attachments as &$attachment) {
                if (!empty($attachment['s3_key'])) {
                    try {
                        $presignedUrl = $this->s3Service->generatePresignedUrl($attachment['s3_key']);
                        
                        if (!$presignedUrl) {
                            throw new Exception("Failed to generate presigned URL for " . $attachment['s3_key']);
                        }
                        
                        $attachment['presigned_url'] = $presignedUrl;
                    } catch (Exception $e) {
                        $this->errorLogController->logError(
                            "Error generating presigned URL: " . $e->getMessage(),
                            __FILE__,
                            __LINE__
                        );
                        $attachment['presigned_url'] = null;
                    }
                }
            }
            
            return $attachments;
        } catch (Exception $e) {
            $this->errorLogController->logError("Error processing attachments: " . $e->getMessage(), __FILE__, __LINE__);
            throw $e;
        }
    }
}
 