<?php

namespace App\Controllers;

use App\Services\EmailService;
use App\Config\Database;
use Exception;

class EmailController {
    private $emailService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailService = new EmailService($db);
    }

    private function validateParams($requiredParams, $data) {
        $missingParams = [];
        foreach ($requiredParams as $param) {
            if (!isset($data[$param])) {
                $missingParams[] = $param;
            }
        }
        if (!empty($missingParams)) {
            error_log('Parâmetros ausentes: ' . implode(', ', $missingParams));
            http_response_code(400);
            echo json_encode(['message' => 'Os seguintes parâmetros estão faltando: ' . implode(', ', $missingParams)]);
            return false;
        }
        return true;
    }

    public function sendMultipleEmails() {
        ob_start();
        header('Content-Type: application/json');
    
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            if (!isset($data['emails']) || !is_array($data['emails'])) {
                http_response_code(400);
                echo json_encode(['message' => 'A lista de e-mails é necessária.']);
                ob_end_flush();
                return;
            }
    
            if (!isset($data['user_id'])) {
                http_response_code(400);
                echo json_encode(['message' => 'O user_id é necessário.']);
                ob_end_flush();
                return;
            }
    
            $user_id = $data['user_id'];
    
            $sendResults = [];
            foreach ($data['emails'] as $emailData) {
                $requiredParams = ['recipientEmails', 'subject', 'htmlTemplate'];
                $missingParams = [];
                foreach ($requiredParams as $param) {
                    if (empty($emailData[$param])) {
                        $missingParams[] = $param;
                    }
                }
    
                if (!empty($missingParams)) {
                    $email = isset($emailData['recipientEmails']) ? $emailData['recipientEmails'] : null;
                    $sendResults[] = [
                        'email' => $email,
                        'result' => 'falhou',
                        'message' => 'Parâmetros obrigatórios ausentes: ' . implode(', ', $missingParams)
                    ];
                    continue;
                }
    
                $recipientEmails = is_array($emailData['recipientEmails']) ? $emailData['recipientEmails'] : [$emailData['recipientEmails']];
                $ccEmails = isset($emailData['ccEmails']) ? (array)$emailData['ccEmails'] : [];
                $bccEmails = isset($emailData['bccEmails']) ? (array)$emailData['bccEmails'] : [];
                $subject = $emailData['subject'];
                $htmlTemplate = $emailData['htmlTemplate'];
                $priority = isset($emailData['priority']) ? (int)$emailData['priority'] : null;
    
                $attachments = [];
                if (isset($emailData['attachments']) && is_array($emailData['attachments'])) {
                    foreach ($emailData['attachments'] as $attachment) {
                        if (isset($attachment['name'], $attachment['mimetype'], $attachment['base64'])) {
                            $decodedContent = base64_decode($attachment['base64'], true);
                            if ($decodedContent === false) {
                                $sendResults[] = [
                                    'email' => $recipientEmails,
                                    'result' => 'falhou',
                                    'message' => "Falha ao decodificar o arquivo anexado: {$attachment['name']}"
                                ];
                                continue 2; 
                            }
                            $attachments[] = [
                                'name' => $attachment['name'],
                                'type' => $attachment['mimetype'],
                                'content' => $decodedContent
                            ];
                        } else {
                            $sendResults[] = [
                                'email' => $recipientEmails,
                                'result' => 'falhou',
                                'message' => 'Dados do anexo incompletos. Nome, mimetype e base64 são necessários.'
                            ];
                            continue 2; 
                        }
                    }
                }
    
                try {
                    $result = $this->emailService->sendEmail(
                        $user_id,
                        $recipientEmails,
                        $subject,
                        $htmlTemplate,
                        null,
                        $priority,
                        $attachments,
                        $ccEmails,
                        $bccEmails
                    );
    
                    $sendResults[] = [
                        'email' => $recipientEmails,
                        'result' => $result['success'] ? 'enviado' : 'falhou',
                        'message' => $result['message'] ?? 'Erro desconhecido'
                    ];
                } catch (Exception $e) {
                    $sendResults[] = [
                        'email' => $recipientEmails,
                        'result' => 'falhou',
                        'message' => 'Erro ao enviar o e-mail: ' . $e->getMessage()
                    ];
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'message' => 'Processamento de envio de e-mails concluído.',
                'results' => $sendResults
            ]);
    
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Erro ao processar os e-mails: ' . $e->getMessage()
            ]);
        }
    
        ob_end_flush(); 
    }
    

    public function sendEmail() {
        ob_start();
        header('Content-Type: application/json');
    
        try {
            if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
                $data = $_POST;
                $attachments = $_FILES['attachments'] ?? [];
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'O conteúdo deve ser multipart/form-data.']);
                ob_end_flush(); 
                return;
            }
    
            $requiredParams = ['user_id', 'recipientEmails', 'subject', 'htmlTemplate'];
            if (!$this->validateParams($requiredParams, $data)) {
                http_response_code(400);
                echo json_encode(['message' => 'Os seguintes parâmetros estão faltando: ' . implode(', ', $requiredParams)]);
                ob_end_flush();  
                return;
            }
    
            $user_id = $data['user_id'];
            $recipientEmails = is_array($data['recipientEmails']) ? $data['recipientEmails'] : [$data['recipientEmails']];
            $ccEmails = isset($data['ccEmails']) ? (is_array($data['ccEmails']) ? $data['ccEmails'] : [$data['ccEmails']]) : [];
            $bccEmails = isset($data['bccEmails']) ? (is_array($data['bccEmails']) ? $data['bccEmails'] : [$data['bccEmails']]) : [];
            $subject = $data['subject'];
            $htmlTemplate = $data['htmlTemplate'];
            $priority = isset($data['priority']) ? (int)$data['priority'] : null;
    
            if ($priority !== null && ($priority < 1 || $priority > 99)) {
                http_response_code(400);
                echo json_encode(['message' => 'A prioridade deve ser um número entre 1 e 99.']);
                ob_end_flush(); 
                return;
            }
    
            $attachmentsArray = [];
            if (!empty($attachments)) {
                foreach ($attachments['tmp_name'] as $key => $tmpName) {
                    if ($attachments['error'][$key] === UPLOAD_ERR_OK) {
                        $attachmentsArray[] = [
                            'name' => $attachments['name'][$key],
                            'type' => $attachments['type'][$key],
                            'tmp_name' => $tmpName,
                            'error' => $attachments['error'][$key],
                            'size' => $attachments['size'][$key],
                        ];
                    } else {
                        http_response_code(400);
                        echo json_encode(['message' => "Erro no upload do anexo: {$attachments['name'][$key]}"]);
                        ob_end_flush();  
                        return;
                    }
                }
            }
    
            $result = $this->emailService->sendEmail(
                $user_id, 
                $recipientEmails, 
                $subject, 
                $htmlTemplate, 
                null, 
                $priority, 
                $attachmentsArray, 
                $ccEmails, 
                $bccEmails
            );
    
            if ($result['success']) {
                http_response_code(200);
                echo json_encode(['message' => 'E-mail(s) adicionado(s) à fila para envio.']);
            } else {
                http_response_code(500);
                echo json_encode(['message' => $result['message']]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao processar o envio de e-mails: ' . $e->getMessage()]);
        }
    
        ob_end_flush(); 
    }

    public function checkDomain($domain) {
        if (empty($domain)) {
            http_response_code(400); // Bad Request
            echo json_encode(['message' => 'Domain is required']);
            return;
        }

        $results = $this->emailService->checkEmailRecords($domain);

        http_response_code(200); 
        echo json_encode($results);
    }

    public function listEmails($user_id, $folder = '*', $search = '') {
        header('Content-Type: application/json');
        $requiredParams = ['user_id'];
        if (!$this->validateParams($requiredParams, ['user_id' => $user_id])) {
            return;
        }
    
        $emails = $this->emailService->listEmails($user_id, $folder, $search);
    
        http_response_code(200); 
        echo json_encode($emails);
    }

    public function viewEmail($email_id) {
        header('Content-Type: application/json');
        $requiredParams = ['email_id'];
        if (!$this->validateParams($requiredParams, ['email_id' => $email_id])) {
            return;
        }

        $email = $this->emailService->viewEmail($email_id);
        if ($email) {
            http_response_code(200);
            echo json_encode($email);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'E-mail não encontrado.']);
        }
    }

    public function markEmailAsSpam($user_id, $provider_id, $email_id): void {
        header('Content-Type: application/json');
        $requiredParams = ['user_id', 'provider_id', 'email_id'];

        if (!$this->validateParams($requiredParams, ['user_id' => $user_id, 'provider_id' => $provider_id, 'email_id' => $email_id])) {
            return;
        }
    
        $result = $this->emailService->markEmailAsSpam($user_id, $provider_id, $email_id);
    
        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => 'E-mail marcado como SPAM.']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao marcar o e-mail como SPAM.']);
        }
    }

    public function deleteSpamEmail($user_id, $email_id) {
        header('Content-Type: application/json');
        $requiredParams = ['user_id', 'email_id'];
        if (!$this->validateParams($requiredParams, ['user_id' => $user_id, 'email_id' => $email_id])) {
            return;
        }

        $result = $this->emailService->deleteSpamEmail($user_id, $email_id);
        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => 'E-mail excluído com sucesso.']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao excluir o e-mail.']);
        }
    }

    public function unmarkSpam($user_id, $email_id, $destinationFolder = 'INBOX') {
        header('Content-Type: application/json');
        $requiredParams = ['user_id', 'email_id'];
        if (!$this->validateParams($requiredParams, ['user_id' => $user_id, 'email_id' => $email_id])) {
            return;
        }

        $result = $this->emailService->unmarkSpam($user_id, $email_id, $destinationFolder);
        if ($result) {
            http_response_code(200);
            echo json_encode(['message' => 'E-mail movido para a pasta ' . $destinationFolder]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao mover o e-mail.']);
        }
    }
}
