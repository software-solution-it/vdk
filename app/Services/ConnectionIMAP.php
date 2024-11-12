<?php
namespace App\Services;

use App\Models\EmailAccount; 
use App\Config\Database;
use App\Helpers\EncryptionHelper;

class ConnectionIMAP {
    private $emailAccountModel;

    public function __construct() { 
        $database = new Database();
        $db = $database->getConnection();
        $this->emailAccountModel = new EmailAccount($db); 
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

            return [
                'status' => true,
                'message' => 'IMAP connection successful',
                'folders' => $folders
            ];
        } else {
            return ['status' => false, 'message' => 'IMAP connection failed: ' . imap_last_error()];
        }
    }
}
