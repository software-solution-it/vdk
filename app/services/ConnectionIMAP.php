<?php
namespace App\Services;

class ConnectionIMAP {
    public function testIMAPConnection($imap_host, $imap_port, $imap_username, $imap_password) {
        $mailbox = "{" . $imap_host . ":" . $imap_port . "/imap/ssl}INBOX";

        $imap = imap_open($mailbox, $imap_username, $imap_password);

        if ($imap) {
            imap_close($imap);
            return ['status' => true, 'message' => 'IMAP connection successful'];
        } else {
            return ['status' => false, 'message' => 'IMAP connection failed: ' . imap_last_error()];
        }
    }
}
