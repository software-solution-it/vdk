<?php

class SMTPService {
    private $smtpConfig;

    public function __construct($smtpConfigModel) {
        $this->smtpConfig = $smtpConfigModel;
    }

    public function createSMTPConfig($user_id, $host, $port, $username, $password, $encryption) {
        return $this->smtpConfig->create($user_id, $host, $port, $username, $password, $encryption);
    }

    public function updateSMTPConfig($id, $host, $port, $username, $password, $encryption) {
        return $this->smtpConfig->update($id, $host, $port, $username, $password, $encryption);
    }

    public function getSMTPConfigByUserId($user_id): mixed {
        return $this->smtpConfig->getByUserId($user_id);
    }

    public function deleteSMTPConfig($id) {
        return $this->smtpConfig->delete($id);
    }
}
