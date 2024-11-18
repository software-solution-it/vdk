<?php
namespace App\Models;

use PDO;
use Exception;
use App\Controllers\ErrorLogController;

class Email {
    private $conn;
    private $table = "emails";
    private $attachmentsTable = "email_attachments";
    private $errorLogController;

    public function __construct($db) {
        $this->conn = $db;
        $this->errorLogController = new ErrorLogController();
    }

    public function saveEmail(
        $user_id, 
        $email_id, 
        $subject, 
        $sender, 
        $recipient, 
        $body_html, 
        $body_text, 
        $date_received, 
        $references, 
        $in_reply_to, 
        $isRead, 
        $folder_id,
        $cc,
        $uid,
        $conversation_id
    ) {
        if (is_null($email_id) || is_null($sender)) {
            return false; 
        }
    
        $subject = $subject ?? 'Sem Assunto';
        $body_html = $body_html ?? null;
        $body_text = $body_text ?? 'Sem Conteúdo';
    
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, email_id, subject, sender, recipient, body_html, body_text, date_received, `references`, in_reply_to, is_read, folder_id, cc, uid, conversation_id) 
                  VALUES 
                  (:user_id, :email_id, :subject, :sender, :recipient, :body_html, :body_text, :date_received, :references, :in_reply_to, :is_read, :folder_id, :cc, :uid, :conversation_id)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':sender', $sender);
        $stmt->bindParam(':recipient', $recipient);
        $stmt->bindParam(':body_html', $body_html);
        $stmt->bindParam(':body_text', $body_text);
        $stmt->bindParam(':date_received', $date_received);
        $stmt->bindParam(':references', $references);
        $stmt->bindParam(':in_reply_to', $in_reply_to);
        $stmt->bindParam(':is_read', $isRead);
        $stmt->bindParam(':folder_id', $folder_id);
        $stmt->bindParam(':cc', $cc);
        $stmt->bindParam(':uid', $uid); 
        $stmt->bindParam(':conversation_id', $conversation_id); 
    
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        } else {
            return false; 
        }
    }
    
    

    public function saveAttachment($email_id, $filename, $mimeType, $size, $content) {
        try {
            $query = "INSERT INTO " . $this->attachmentsTable . " 
                      (email_id, filename, mime_type, size, content) 
                      VALUES 
                      (:email_id, :filename, :mime_type, :size, :content)";
    
            $stmt = $this->conn->prepare($query);
    
            $stmt->bindParam(':email_id', $email_id);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':mime_type', $mimeType);
            $stmt->bindParam(':size', $size);
            $stmt->bindParam(':content', $content, PDO::PARAM_LOB);
    
            return $stmt->execute();
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao salvar o anexo: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao salvar o anexo: ' . $e->getMessage());
        }
    }

    public function updateEmailAfterMove($oldMessageId, $newMessageId, $folderName) {
        $sql = "UPDATE emails SET conversation_Id = :new_conversation_Id, folder = :folder_name WHERE conversation_Id = :old_conversation_Id";
        $stmt = $this->conn->prepare($sql); 
        $stmt->bindParam(':new_conversation_Id', $newMessageId);
        $stmt->bindParam(':folder_name', $folderName);
        $stmt->bindParam(':old_conversation_Id', $oldMessageId);
        $stmt->execute();
    }
    

    public function getLastEmailSyncDateByFolder($user_id, $folder) {
        $query = "SELECT MAX(date_received) as last_date 
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id AND folder = :folder";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':folder', $folder);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_date'] ?? null; 
    }

    public function updateLastEmailSyncDateByFolder($user_id, $folder, $lastEmailDate) {
        $query = "UPDATE " . $this->table . " 
                  SET date_received = :last_email_date 
                  WHERE user_id = :user_id 
                  AND folder = :folder 
                  AND date_received = (
                      SELECT max_date FROM (
                          SELECT MAX(date_received) AS max_date FROM " . $this->table . " 
                          WHERE user_id = :user_id AND folder = :folder
                      ) AS temp_table
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':last_email_date', $lastEmailDate);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':folder', $folder);
        
        return $stmt->execute();
    }

    // Método para verificar se um e-mail já existe
    public function emailExists($email_id, $user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email_id = :email_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_id', $email_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    // Método para obter um e-mail pelo messageId e user_id
    public function getEmailByMessageId($messageId, $user_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE email_id = :conversation_Id AND user_id = :user_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':conversation_Id', $messageId);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
    
            return $stmt->fetch(PDO::FETCH_ASSOC);
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar e-mail por message ID: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao buscar e-mail por message ID: ' . $e->getMessage());
        }
    }

    // Método para atualizar um e-mail existente
    public function updateEmail(
        $id, 
        $user_id, 
        $email_id, 
        $subject, 
        $sender, 
        $recipient, 
        $body, 
        $date_received, 
        $references, 
        $in_reply_to, 
        $isRead, 
        $folder,
        $cc,
        $uid,
        $conversation_id
    ) {
        try {
            $query = "UPDATE " . $this->table . " SET
                        subject = :subject,
                        sender = :sender,
                        recipient = :recipient,
                        body = :body,
                        date_received = :date_received,
                        `references` = :references,
                        in_reply_to = :in_reply_to,
                        is_read = :is_read,
                        folder = :folder,
                        cc = :cc,
                        uid = :uid,
                        conversation_id = :conversation_id
                      WHERE id = :id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':sender', $sender);
            $stmt->bindParam(':recipient', $recipient);
            $stmt->bindParam(':body', $body);
            $stmt->bindParam(':date_received', $date_received);
            $stmt->bindParam(':references', $references);
            $stmt->bindParam(':in_reply_to', $in_reply_to);
            $stmt->bindParam(':is_read', $isRead);
            $stmt->bindParam(':folder', $folder);
            $stmt->bindParam(':cc', $cc);
            $stmt->bindParam(':uid', $uid);
            $stmt->bindParam(':conversation_id', $conversation_id);
    
            return $stmt->execute();
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao atualizar e-mail: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao atualizar e-mail: ' . $e->getMessage());
        }
    }

    // Método para atualizar a pasta de um e-mail
    public function updateFolder($messageId, $folderName) {
        try {
            $query = "UPDATE " . $this->table . " SET folder = :folder_name WHERE email_id = :conversation_Id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folder_name', $folderName);
            $stmt->bindParam(':conversation_Id', $messageId);
    
            return $stmt->execute();
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao atualizar a pasta do e-mail: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao atualizar a pasta do e-mail: ' . $e->getMessage());
        }
    }

    public function getEmailIdsByFolder($user_id, $folder) {
        try {
            $query = "SELECT email_id FROM " . $this->table . " WHERE user_id = :user_id AND folder = :folder";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':folder', $folder);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar email_ids por pasta: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao buscar email_ids por pasta: ' . $e->getMessage());
        }
    }
    

    public function deleteEmailByMessageId($messageId, $user_id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE email_id = :message_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':message_id', $messageId);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao deletar e-mail por Message-ID: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao deletar e-mail por Message-ID: ' . $e->getMessage());
        }
    }

    // Método para deletar um e-mail
    public function deleteEmail($messageId) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE email_id = :conversation_Id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':conversation_Id', $messageId);
    
            return $stmt->execute();
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao deletar e-mail: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao deletar e-mail: ' . $e->getMessage());
        }
    }


    public function getEmailsByConversationId($user_id, $conversation_id)
    {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE user_id = :user_id AND conversation_id = :conversation_id
                      ORDER BY date_received ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->execute();
    
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Error fetching emails by conversation ID: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Error fetching emails by conversation ID: ' . $e->getMessage());
        }
    }
    
    public function emailExistsByMessageId($messageId, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email_id = :conversation_Id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':conversation_Id', $messageId);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            return $result['count'] > 0;
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao verificar existência de e-mail por Message-ID: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao verificar existência de e-mail por Message-ID: ' . $e->getMessage());
        }
    }
    

    private function getEmailsByIds($emailIds) {
        try {
            $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
            $query = "SELECT * FROM " . $this->table . " WHERE id IN ($placeholders)";
            $stmt = $this->conn->prepare($query);
            foreach ($emailIds as $index => $id) {
                $stmt->bindValue($index + 1, $id);
            }
            $stmt->execute();
    
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar e-mails por IDs: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao buscar e-mails por IDs: ' . $e->getMessage());
        }
    }

    public function updateLabel($emailId, $newLabel) {
        try {
            $query = "UPDATE emails SET folder_name = :folder_name WHERE id = :email_id";
            $stmt = $this->conn->prepare($query);
    
            $stmt->bindParam(':folder_name', $newLabel);
            $stmt->bindParam(':email_id', $emailId);
    
            if ($stmt->execute()) {
                return true;
            } else {
                throw new Exception("Erro ao atualizar o rótulo do e-mail com ID: $emailId");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }


    public function getLastEmailSyncDateByFolderId($user_id, $folder_id) {
        try {
            $query = "SELECT MAX(date_received) as last_date 
                      FROM " . $this->table . " 
                      WHERE user_id = :user_id AND folder_id = :folder_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['last_date'] ?? null;
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar a última data de sincronização do e-mail por Folder ID: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao buscar a última data de sincronização do e-mail por Folder ID: ' . $e->getMessage());
        }
    }
    
    public function getEmailIdsByFolderId($user_id, $folder_id) {
        try {
            $query = "SELECT email_id FROM " . $this->table . " 
                      WHERE user_id = :user_id AND folder_id = :folder_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar os IDs dos e-mails por Folder ID: ' . $e->getMessage(), __FILE__, __LINE__, $user_id);
            throw new Exception('Erro ao buscar os IDs dos e-mails por Folder ID: ' . $e->getMessage());
        }
    }
    

    public function getEmailById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
    
            return $stmt->fetch(PDO::FETCH_ASSOC);
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar e-mail por ID: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao buscar e-mail por ID: ' . $e->getMessage());
        }
    }


    public function getDnsRecords($domain) {
        return dns_get_record($domain, DNS_TXT);
    }

    public function checkDkim($domain) {
        $dkimSelector = 'default';
        $dkimRecord = dns_get_record("{$dkimSelector}._domainkey.{$domain}", DNS_TXT);
    
        return !empty($dkimRecord) ? $dkimRecord : null;
    }

    public function checkDmarc($domain) {
        $dmarcRecord = dns_get_record("_dmarc.{$domain}", DNS_TXT);
        return !empty($dmarcRecord) ? $dmarcRecord : null;
    }

    public function checkSpf($domain) {
        $spfRecords = dns_get_record($domain, DNS_TXT);
        $spfRecords = array_filter($spfRecords, function($record) {
            return strpos($record['txt'], 'v=spf1') !== false; 
        });
        return !empty($spfRecords) ? $spfRecords : null;
    }

    public function getLastEmailDate() {
        $query = "SELECT MAX(date_received) as last_date FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['last_date'] ?? null;
    }

    public function getLastEmailSyncDate($user_id) {
        $query = "SELECT MAX(date_received) as last_date 
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_date'] ?? null; 
    }
    
    public function getEmailsByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
