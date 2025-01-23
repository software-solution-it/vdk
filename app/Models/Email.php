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


    public function getConversationByInReplyTo($in_reply_to) {
        try {
            $query = "
                SELECT 
                    conversation_id, 
                    MAX(conversation_step) AS max_step 
                FROM mail.emails 
                WHERE email_id = :in_reply_to 
                GROUP BY conversation_id
            ";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':in_reply_to', $in_reply_to, PDO::PARAM_STR);
            $stmt->execute();
    
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result) {
                return $result;
            }
    
            return null;
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao buscar conversa por in_reply_to: " . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception("Erro ao buscar conversa por in_reply_to: " . $e->getMessage());
        }
    }

    public function attachmentExists($email_account_id, $filename) {
        try {
            $query = "SELECT COUNT(*) FROM {$this->attachmentsTable} 
                      WHERE email_account_id = :email_account_id 
                      AND filename = :filename";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_account_id', $email_account_id);
            $stmt->bindParam(':filename', $filename);
            $stmt->execute();

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao verificar existência de anexo: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
            throw $e;
        }
    }


    public function getFolderNameById($folder_id) {
        $query = "SELECT folder_name FROM email_folders WHERE id = :folder_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
        $stmt->execute();
    
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['folder_name'] : null;
    }
    
    
    

    public function saveEmail(
        $user_id, 
        $email_account_id,
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
        $conversation_id,
        $conversation_step,
        $from
    ) {
        if (is_null($email_id) || is_null($sender)) {
            return false; 
        }

        $isRead = ($isRead === null || $isRead === '') ? 0 : (int) $isRead;
    
        $subject = $subject ?? 'Sem Assunto';
        $body_html = $body_html ?? null;
        $body_text = $body_text ?? 'Sem Conteúdo';
    
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, email_account_id, email_id, subject, sender, recipient, body_html, body_text, date_received, `references`, in_reply_to, is_read, folder_id, cc, uid, conversation_id, conversation_step, `from`) 
                  VALUES 
                  (:user_id, :email_account_id, :email_id, :subject, :sender, :recipient, :body_html, :body_text, :date_received, :references, :in_reply_to, :is_read, :folder_id, :cc, :uid, :conversation_id, :conversation_step, :from)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email_account_id', $email_account_id); // Novo campo
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
        $stmt->bindParam(':conversation_step', $conversation_step);
        $stmt->bindParam(':from', $from);
    
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        } else {
            return false; 
        }
    }

    public function updateEmailBodyHtml($emailId, $body_html) {
        try {
            $query = "UPDATE " . $this->table . " SET body_html = :body_html WHERE email_id = :email_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':body_html', $body_html, PDO::PARAM_STR);
            $stmt->bindParam(':email_id', $emailId, PDO::PARAM_STR);
    
            if ($stmt->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            $this->errorLogController->logError("Erro ao atualizar body_html do email: " . $e->getMessage(), __FILE__, __LINE__, null);
            throw new Exception("Erro ao atualizar body_html do email: " . $e->getMessage());
        }
    }
     
    
    
    
    
    

    public function saveAttachment($emailId, $filename, $mimeType, $size, $s3Key, $contentHash)
    {
        try {
            $query = "INSERT INTO email_attachments 
                        (email_id, filename, mime_type, size, s3_key, content_hash) 
                     VALUES 
                        (:email_id, :filename, :mime_type, :size, :s3_key, :content_hash)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':email_id' => $emailId,
                ':filename' => $filename,
                ':mime_type' => $mimeType,
                ':size' => $size,
                ':s3_key' => $s3Key,
                ':content_hash' => $contentHash
            ]);
            
            return $this->conn->lastInsertId();
        } catch (\Exception $e) {
            error_log("Erro ao salvar anexo: " . $e->getMessage());
            throw $e;
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
    public function getEmailByMessageId($messageId, $email_account_id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE email_id = :conversation_Id AND email_account_id = :email_account_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':conversation_Id', $messageId);
            $stmt->bindParam(':email_account_id', $email_account_id);
            $stmt->execute();
    
            return $stmt->fetch(PDO::FETCH_ASSOC);
    
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao buscar e-mail por message ID: ' . $e->getMessage(), __FILE__, __LINE__, null);
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

    // Add this new method for sync updates
    public function updateEmailSync($id, $isRead, $folderId, $isFavorite) {
        try {
            $query = "UPDATE " . $this->table . " SET
                        is_read = :is_read,
                        folder_id = :folder_id,
                        is_favorite = :is_favorite,
                        updated_at = NOW()
                      WHERE id = :id";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':is_read', $isRead);
            $stmt->bindParam(':folder_id', $folderId);
            $stmt->bindParam(':is_favorite', $isFavorite);

            return $stmt->execute();
        } catch (Exception $e) {
            $this->errorLogController->logError('Erro ao atualizar e-mail: ' . $e->getMessage(), __FILE__, __LINE__);
            throw new Exception('Erro ao atualizar e-mail: ' . $e->getMessage());
        }
    }

    public function updateEmailOrder($messageId, $user_id, $order)
    {
        try {
            $sql = "UPDATE emails SET `order` = :order WHERE message_id = :message_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':order', $order, PDO::PARAM_INT);
            $stmt->bindParam(':message_id', $messageId, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            error_log("Ordem atualizada para o e-mail com Message-ID $messageId: ordem = $order");
        } catch (Exception $e) {
            error_log("Erro ao atualizar a ordem do e-mail: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateFolder($messageId, $folderId) {
        try {
            $query = "UPDATE " . $this->table . " SET folder_id = :folder_id WHERE email_id = :email_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folder_id', $folderId);
            $stmt->bindParam(':email_id', $messageId);
    
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
    


    public function emailExistsByMessageId($messageId, $email_account_id) {
    
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email_id = :messageId AND email_account_id = :email_account_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':messageId', $messageId);
            $stmt->bindParam(':email_account_id', $email_account_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
    
        } catch (Exception $e) {
            throw new Exception('Erro ao verificar existência de e-mail por Message-ID: ' . $e->getMessage());
        }
    }

    public function emailExistsInFolder($messageId, $email_account_id, $folderName) {
        try {
            $query = "
                SELECT COUNT(*) as count 
                FROM emails e
                INNER JOIN email_folders ef ON e.folder_id = ef.id
                WHERE e.email_id = :messageId 
                AND e.email_account_id = :email_account_id 
                AND ef.folder_name = :folderName
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':messageId', $messageId, PDO::PARAM_STR);
            $stmt->bindParam(':email_account_id', $email_account_id, PDO::PARAM_INT);
            $stmt->bindParam(':folderName', $folderName, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
    
        } catch (Exception $e) {
            throw new Exception('Erro ao verificar existência de e-mail na pasta: ' . $e->getMessage());
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
            // Consulta SQL para obter o e-mail e adicionar a ordem pela data de recebimento
            $query = "
                SELECT e.*, ef.folder_name,
                       (@rownum := @rownum + 1) AS `order`
                FROM " . $this->table . " e
                INNER JOIN email_folders ef ON e.folder_id = ef.id
                WHERE e.id = :id
                ORDER BY e.date_received ASC
            ";
    
            // Inicia a variável para rastrear a ordem
            $this->conn->exec("SET @rownum := 0");
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
    
            return $stmt->fetch(PDO::FETCH_ASSOC);
        
        } catch (Exception $e) {
            // Log de erro
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

    public function getEmailsByEmailAccountId($email_account_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE email_account_id = :email_account_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email_account_id', $email_account_id);
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAttachmentByHash($contentHash)
    {
        try {
            $query = "SELECT * FROM email_attachments WHERE content_hash = :content_hash LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':content_hash' => $contentHash]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Erro ao buscar anexo por hash: " . $e->getMessage());
            return null;
        }
    }

    public function toggleFavorite($email_id, $email_account_id) {
        try {
            // Primeiro, verifica se o email existe
            $query = "SELECT is_favorite, user_id FROM " . $this->table . " 
                     WHERE id = :email_id AND email_account_id = :email_account_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email_id', $email_id);
            $stmt->bindParam(':email_account_id', $email_account_id);
            $stmt->execute();
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                throw new Exception("Email não encontrado");
            }

            // Toggle the value
            $newValue = isset($current['is_favorite']) ? !$current['is_favorite'] : true;

            // Update the value
            $query = "UPDATE " . $this->table . " 
                     SET is_favorite = :is_favorite 
                     WHERE id = :email_id AND email_account_id = :email_account_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':is_favorite', $newValue, PDO::PARAM_BOOL);
            $stmt->bindParam(':email_id', $email_id);
            $stmt->bindParam(':email_account_id', $email_account_id);
            
            if ($stmt->execute()) {
                return [
                    'status' => true, 
                    'is_favorite' => $newValue,
                    'message' => 'Status de favorito atualizado com sucesso'
                ];
            }

            return [
                'status' => false, 
                'message' => 'Falha ao atualizar favorito'
            ];

        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao alternar favorito: " . $e->getMessage(),
                __FILE__,
                __LINE__,
                $current['user_id'] ?? null
            );
            throw $e;
        }
    }

    public function getAttachmentById($attachment_id) {
        try {
            $query = "
                SELECT 
                    id, 
                    mime_type,
                    filename,
                    content,
                    s3_key,
                    content_hash,
                    size
                FROM email_attachments
                WHERE id = :attachment_id
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':attachment_id', $attachment_id, PDO::PARAM_INT);
            $stmt->execute();
    
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$attachment) {
                throw new Exception("Anexo não encontrado.");
            }

            // Se tiver s3_key, gera URL pré-assinada usando o AwsCredential
            if (!empty($attachment['s3_key'])) {
                try {
                    $awsCredential = new AwsCredential($this->conn);
                    $credentials = $awsCredential->getCredentials();
                    
                    if (!$credentials) {
                        throw new Exception("Credenciais AWS não encontradas");
                    }

                    $s3Client = new \Aws\S3\S3Client([
                        'version' => 'latest',
                        'region'  => $credentials['region'],
                        'credentials' => [
                            'key'    => $credentials['access_key_id'],
                            'secret' => $credentials['secret_access_key'],
                        ]
                    ]);

                    $command = $s3Client->getCommand('GetObject', [
                        'Bucket' => $credentials['bucket'],
                        'Key'    => $attachment['s3_key']
                    ]);

                    $request = $s3Client->createPresignedRequest($command, '+1 hour');
                    $attachment['presigned_url'] = (string) $request->getUri();
                    unset($attachment['content']);
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
            $this->errorLogController->logError(
                "Erro ao buscar o anexo: " . $e->getMessage(), 
                __FILE__, 
                __LINE__, 
                null
            );
            throw new Exception("Erro ao buscar o anexo: " . $e->getMessage());
        }
    }

    public function getEmailsByFolder($folder_id, $page = 1, $per_page = 10, $order = 'DESC', $orderBy = 'date') {
        try {
            $offset = ($page - 1) * $per_page;
            
            $query = "
                SELECT 
                    e.*,
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
                        FROM " . $this->attachmentsTable . " a 
                        WHERE a.email_id = e.id
                    ) as attachments
                FROM " . $this->table . " e
                WHERE e.folder_id = :folder_id";

            // Adiciona ordenação baseada no parâmetro orderBy
            if (strtolower($orderBy) === 'favorite') {
                $query .= " ORDER BY e.is_favorite " . $order . ", e.date_received " . $order;
            } else {
                $query .= " ORDER BY e.date_received " . $order;
            }

            $query .= " LIMIT :per_page OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Processar anexos e adicionar URLs pré-assinadas
            foreach ($emails as &$email) {
                if (!empty($email['attachments'])) {
                    $attachments = json_decode($email['attachments'], true);
                    if (is_array($attachments)) {
                        foreach ($attachments as &$attachment) {
                            if (!empty($attachment['s3_key'])) {
                                try {
                                    // Verificar se o objeto existe no S3 antes de gerar a URL
                                    $awsCredential = new AwsCredential($this->conn);
                                    $credentials = $awsCredential->getCredentials();
                                    
                                    if (!$credentials) {
                                        throw new Exception("Credenciais AWS não encontradas");
                                    }

                                    $s3Client = new \Aws\S3\S3Client([
                                        'version' => 'latest',
                                        'region'  => $credentials['region'],
                                        'credentials' => [
                                            'key'    => $credentials['access_key_id'],
                                            'secret' => $credentials['secret_access_key'],
                                        ]
                                    ]);

                                    // Verificar se o objeto existe
                                    try {
                                        $s3Client->headObject([
                                            'Bucket' => $credentials['bucket'],
                                            'Key'    => $attachment['s3_key']
                                        ]);

                                        // Se chegou aqui, o objeto existe
                                        $command = $s3Client->getCommand('GetObject', [
                                            'Bucket' => $credentials['bucket'],
                                            'Key'    => $attachment['s3_key']
                                        ]);

                                        $request = $s3Client->createPresignedRequest($command, '+1 hour');
                                        $attachment['presigned_url'] = (string) $request->getUri();
                                    } catch (\Aws\S3\Exception\S3Exception $e) {
                                        // Se o objeto não existe, tenta um caminho alternativo
                                        $alternativePath = 'attachments/' . basename(dirname($attachment['s3_key'])) . '/' . basename($attachment['s3_key']);
                                        
                                        try {
                                            $s3Client->headObject([
                                                'Bucket' => $credentials['bucket'],
                                                'Key'    => $alternativePath
                                            ]);

                                            // Se chegou aqui, o objeto existe no caminho alternativo
                                            $command = $s3Client->getCommand('GetObject', [
                                                'Bucket' => $credentials['bucket'],
                                                'Key'    => $alternativePath
                                            ]);

                                            $request = $s3Client->createPresignedRequest($command, '+1 hour');
                                            $attachment['presigned_url'] = (string) $request->getUri();
                                            
                                            // Atualizar o s3_key no banco de dados
                                            $this->updateAttachmentS3Key($attachment['id'], $alternativePath);
                                        } catch (\Aws\S3\Exception\S3Exception $e2) {
                                            $this->errorLogController->logError(
                                                "Arquivo não encontrado no S3: " . $attachment['s3_key'] . " nem em " . $alternativePath,
                                                __FILE__,
                                                __LINE__
                                            );
                                            $attachment['presigned_url'] = null;
                                        }
                                    }
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
                        $email['attachments'] = $attachments;
                    } else {
                        $email['attachments'] = [];
                    }
                } else {
                    $email['attachments'] = [];
                }
            }

            // Obter contagem total para paginação
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE folder_id = :folder_id";
            $countStmt = $this->conn->prepare($countQuery);
            $countStmt->bindParam(':folder_id', $folder_id, PDO::PARAM_INT);
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Sanitize and decode HTML entities in the results
            foreach ($emails as &$email) {
                if (isset($email['body_html'])) {
                    $email['body_html'] = html_entity_decode($email['body_html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (isset($email['body_text'])) {
                    $email['body_text'] = html_entity_decode($email['body_text'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); 
                }
                if (isset($email['subject'])) {
                    $email['subject'] = html_entity_decode($email['subject'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            return [
                'total' => (int)$totalCount,
                'emails' => $emails
            ];

        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao buscar e-mails por pasta: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
            throw new Exception("Erro ao buscar e-mails por pasta: " . $e->getMessage());
        }
    }

    // Adicionar este novo método para atualizar o s3_key
    private function updateAttachmentS3Key($attachment_id, $new_s3_key) {
        try {
            $query = "UPDATE " . $this->attachmentsTable . " 
                     SET s3_key = :s3_key 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':s3_key', $new_s3_key);
            $stmt->bindParam(':id', $attachment_id);
            $stmt->execute();
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Erro ao atualizar s3_key do anexo: " . $e->getMessage(),
                __FILE__,
                __LINE__
            );
        }
    }

};

