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

    public function testIMAPConnection($email, $password, $imap_host, $imap_port, $encryption) {
        try {
            $mailbox = "{" . $imap_host . ":" . $imap_port . "/imap/" . $encryption . "}";
    
            $imap = imap_open($mailbox, $email, $password);
    
            if ($imap) {
                $folders = imap_list($imap, $mailbox, '*');
                imap_close($imap);
    
                if ($folders === false) {
                    return ['status' => false, 'message' => 'Failed to retrieve folder list: ' . imap_last_error()];
                }
    
                $folderNames = array_map(function($folder) use ($mailbox) {
                    return str_replace($mailbox, '', $folder);
                }, $folders);
    
                return [
                    'status' => true,
                    'message' => 'IMAP connection successful and folders retrieved',
                    'folders' => $folderNames
                ];
            } else {
                return ['status' => false, 'message' => 'IMAP connection failed: ' . imap_last_error()];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage()];
        }
    }
    
}
