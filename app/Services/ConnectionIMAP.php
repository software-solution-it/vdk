<?php
namespace App\Services;

use App\Models\EmailAccount; 
use App\Models\EmailFolder;
use App\Config\Database;
use App\Helpers\EncryptionHelper;

class ConnectionIMAP {
    private $emailAccountModel;
    private $conn;

    public function __construct() { 
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->emailAccountModel = new EmailAccount($this->conn); 
    }

    public function testIMAPConnection($user_id, $email_id) {
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $email_id);
        
        if (!$emailAccount) {
            return ['status' => false, 'message' => 'Email account not found'];
        }
    
        $imap_host = $emailAccount['imap_host'];
        $imap_port = $emailAccount['imap_port'];
        $imap_username = $emailAccount['email'];
        $imap_password = EncryptionHelper::decrypt($emailAccount['password']);
    
        $mailbox = "{" . $imap_host . ":" . $imap_port . "/imap/ssl}";
    
        $imap = imap_open($mailbox, $imap_username, $imap_password);
    
        if ($imap) {
            $folders = imap_list($imap, $mailbox, '*');
            imap_close($imap);
    
            if ($folders === false) {
                return ['status' => false, 'message' => 'Failed to retrieve folder list: ' . imap_last_error()];
            }
    
            $folderNames = array_map(function($folder) use ($mailbox) {
                return str_replace($mailbox, '', $folder);
            }, $folders);
    
            $emailFolderModel = new EmailFolder($this->conn);
            $emailFolderModel->syncFolders($email_id, $folderNames);
    
            return [
                'status' => true,
                'message' => 'IMAP connection successful and folders saved',
                'folders' => $folderNames
            ];
        } else {
            return ['status' => false, 'message' => 'IMAP connection failed: ' . imap_last_error()];
        }
    }
}
