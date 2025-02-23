<?php

namespace App\Controllers;

use App\Services\EmailService;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;
use App\Models\EmailFolder;

class EmailController {
    private $emailService;
    private $errorLogController;

    private $emailFolderModel;


    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->emailService = new EmailService($db);
        $this->errorLogController = new ErrorLogController();
        $this->emailFolderModel = new EmailFolder($db);
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
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Os seguintes parâmetros estão faltando: ' . implode(', ', $missingParams),
                'Data' => null
            ]);
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
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'A lista de e-mails é necessária.',
                    'Data' => null
                ]);
                ob_end_flush();
                return;
            }
    
            if (!isset($data['user_id'])) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'O user_id é necessário.',
                    'Data' => null
                ]);
                ob_end_flush();
                return;
            }
    
            if (!isset($data['email_account_id'])) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'O email_account_id é necessário.',
                    'Data' => null
                ]);
                ob_end_flush();
                return;
            }
    
            $user_id = $data['user_id'];
            $email_account_id = $data['email_account_id'];
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
    
                $inReplyTo = isset($emailData['inReplyTo']) ? $emailData['inReplyTo'] : null;
    
                try {
                    $result = $this->emailService->sendEmail(
                        $user_id,
                        $email_account_id,
                        $recipientEmails,
                        $subject,
                        $htmlTemplate,
                        "",
                        $priority,
                        $attachments,
                        $ccEmails,
                        $bccEmails,
                        $inReplyTo
                    );
    
                    $sendResults[] = [
                        'email' => $recipientEmails,
                        'result' => $result['success'] ? 'enviado' : 'falhou',
                        'message' => $result['message'] ?? 'Erro desconhecido'
                    ];
                } catch (Exception $e) {
                    $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
                    $sendResults[] = [
                        'email' => $recipientEmails,
                        'result' => 'falhou',
                        'message' => 'Erro ao enviar o e-mail: ' . $e->getMessage()
                    ];
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Processamento de envio de e-mails concluído.',
                'Data' => $sendResults
            ]);
    
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao processar os e-mails: ' . $e->getMessage(),
                'Data' => null
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
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'O conteúdo deve ser multipart/form-data.',
                    'Data' => null
                ]);
                ob_end_flush(); 
                return;
            }
    
            $requiredParams = ['user_id', 'email_account_id', 'recipientEmails', 'subject', 'htmlTemplate'];
            if (!$this->validateParams($requiredParams, $data)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Os seguintes parâmetros estão faltando: ' . implode(', ', $requiredParams),
                    'Data' => null
                ]);
                ob_end_flush();  
                return;
            }
    
            $user_id = $data['user_id'];
            $email_account_id = $data['email_account_id'];
            $recipientEmails = is_array($data['recipientEmails']) ? $data['recipientEmails'] : [$data['recipientEmails']];
            $ccEmails = isset($data['ccEmails']) ? (is_array($data['ccEmails']) ? $data['ccEmails'] : [$data['ccEmails']]) : [];
            $bccEmails = isset($data['bccEmails']) ? (is_array($data['bccEmails']) ? $data['bccEmails'] : [$data['bccEmails']]) : [];
            $subject = $data['subject'];
            $htmlTemplate = $data['htmlTemplate'];
            $priority = isset($data['priority']) ? (int)$data['priority'] : null;
    
            if ($priority !== null && ($priority < 1 || $priority > 99)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'A prioridade deve ser um número entre 1 e 99.',
                    'Data' => null
                ]);
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
                        echo json_encode([
                            'Status' => 'Error',
                            'Message' => "Erro no upload do anexo: {$attachments['name'][$key]}",
                            'Data' => null
                        ]);
                        ob_end_flush();  
                        return;
                    }
                }
            }
    
            $result = $this->emailService->sendEmail(
                $user_id,
                $email_account_id,
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
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'E-mail(s) adicionado(s) à fila para envio.',
                    'Data' => null
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => $result['message'],
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao processar o envio de e-mails: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    
        ob_end_flush(); 
    }

    public function checkDomain($domain) {
        header('Content-Type: application/json');
        if (empty($domain)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Domain is required',
                'Data' => null
            ]);
            return;
        }

        try {
            $results = $this->emailService->checkEmailRecords($domain);
            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Domain check successful.',
                'Data' => $results
            ]);
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao verificar o domínio: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }


    public function listEmails($folder_id = null, $folder_name = null) {
        // Define o cabeçalho como JSON logo no início
        header('Content-Type: application/json; charset=utf-8');

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';
            $orderBy = isset($_GET['orderby']) ? strtolower($_GET['orderby']) : 'date';

            if (!in_array($order, ['ASC', 'DESC'])) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Parâmetro order inválido. Use ASC ou DESC',
                    'Data' => null
                ]);
                return;
            }

            if (!in_array($orderBy, ['date', 'favorite'])) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Parâmetro orderby inválido. Use date ou favorite',
                    'Data' => null
                ]);
                return;
            }

            $offset = ($page - 1) * $limit;
            
            $result = $this->emailService->listEmails($folder_id, $folder_name, $limit, $offset, $order, $orderBy);

            http_response_code(200);
            echo json_encode([
                'Status' => 'Success',
                'Message' => 'Emails retrieved successfully',
                'Data' => $result
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    
    

    public function getAttachmentById($attachment_id) {
        header('Content-Type: application/json');

        try {
            $attachment = $this->emailService->getAttachmentById($attachment_id);
            if ($attachment) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Anexo recuperado com sucesso.',
                    'Data' => $attachment
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Anexo não encontrado.',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao buscar o anexo: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }

    public function deleteEmail() {
        header('Content-Type: application/json');
    
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            $requiredParams = ['email_id'];
            if (!$this->validateParams($requiredParams, $data)) {
                return;
            }
    
            $email_id = $data['email_id'];
    
            $result = $this->emailService->deleteEmail($email_id);
    
            if ($result) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'E-mail excluído com sucesso.',
                    'Data' => null
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Falha ao excluir o e-mail. Talvez ele já tenha sido removido.',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao excluir o e-mail: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    
    
    
    public function moveEmail() {
        header('Content-Type: application/json');
    
        try {
            // Recebe os dados da requisição
            $data = json_decode(file_get_contents('php://input'), true);
    
            // Verifica se o parâmetro obrigatório email_id está presente
            $requiredParams = ['email_id'];
            if (!$this->validateParams($requiredParams, $data)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Parâmetros obrigatórios ausentes.',
                    'Data' => null
                ]);
                return;
            }
    
            $email_id = $data['email_id'];
            $folder_id = isset($data['folder_id']) ? $data['folder_id'] : null;
            $folder_name = isset($data['folder_name']) ? $data['folder_name'] : null;
    
            // Verifica se ao menos um dos parâmetros (folder_id ou folder_name) foi fornecido
            if (!$folder_id && !$folder_name) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'É obrigatório informar folder_id ou folder_name.',
                    'Data' => null
                ]);
                return;
            }
    
            if (!$folder_id && $folder_name) {
                $folderDetails = $this->emailFolderModel->getByFolderName($folder_name); 
                
                if (!$folderDetails) {
                    http_response_code(400);
                    echo json_encode([
                        'Status' => 'Error',
                        'Message' => 'Pasta não encontrada com o nome informado.',
                        'Data' => null
                    ]);
                    return;
                }
            
                $folder_id = $folderDetails['id'];  
                $folder_name = $folderDetails['folder_name'];
            }
    
            if ($folder_id && !$folder_name) {
                $folderDetails = $this->emailFolderModel->getFolderById($folder_id);
                if (!$folderDetails) {
                    http_response_code(400);
                    echo json_encode([
                        'Status' => 'Error',
                        'Message' => 'Pasta não encontrada com o folder_id informado.',
                        'Data' => null
                    ]);
                    return;
                }
                $folder_name = $folderDetails['folder_name'];  
            }
    
            $this->errorLogController->logError('Tentando mover o e-mail. ID do E-mail: ' . $email_id . ' para a pasta: ' . $folder_name, __FILE__, __LINE__);
    
            $result = $this->emailService->moveEmail($email_id, $folderDetails['folder_name']);
    
            if ($result) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'E-mail movido com sucesso.' . $folderDetails['id'] . $folderDetails['folder_name'],
                    'Data' => null
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Falha ao mover o e-mail.',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            // Log de erro
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Erro ao mover o e-mail: ' . $e->getMessage(),
                'Data' => null
            ]);
        }
    }
    
    
    
    

    public function viewEmail($email_id) {
        // Define o cabeçalho como JSON logo no início
        header('Content-Type: application/json; charset=utf-8');
        
        $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
    
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => "Invalid 'order' parameter. Use 'ASC' or 'DESC'.",
                'Data' => null
            ]);
            return;
        }
    
        $requiredParams = ['email_id'];
        if (!$this->validateParams($requiredParams, ['email_id' => $email_id])) {
            return;
        }
    
        try {
            $email = $this->emailService->viewEmailThread($email_id, $order);
    
            if ($email) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Email retrieved successfully.',
                    'Data' => $email
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Email not found.',
                    'Data' => null
                ]);
            }
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'An error occurred while retrieving the email: ' . $e->getMessage(),
                'Data' => null
            ]); 
        }
    }
    
    
    public function toggleFavorite() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['email_id']) || !isset($data['email_account_id'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'email_id e email_account_id são obrigatórios'
                ]);
                return;
            }

            $result = $this->emailService->toggleFavorite(
                $data['email_id'],
                $data['email_account_id']
            );

            http_response_code(200);
            echo json_encode($result);

        } catch (Exception $e) {
            http_response_code($e->getMessage() === "Email não encontrado" ? 404 : 500);
            echo json_encode([
                'status' => false,
                'message' => 'Erro ao favoritar email: ' . $e->getMessage()
            ]);
        }
    }

    public function getAttachmentsByEmailId($email_id) {
        try {
            $attachments = $this->emailService->getAttachmentsByEmailId($email_id);
            
            // Define o cabeçalho como JSON
            header('Content-Type: application/json');
            
            http_response_code(200);
            echo json_encode([
                'status' => true,
                'message' => 'Anexos recuperados com sucesso',
                'data' => [
                    'total' => count($attachments),
                    'attachments' => $attachments
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            $this->errorLogController->logError($e->getMessage(), __FILE__, __LINE__);
            
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'Erro ao buscar anexos: ' . $e->getMessage(),
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
