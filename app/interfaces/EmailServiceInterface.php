<?php

interface EmailServiceInterface {
    public function saveEmail($data);
    public function readEmails($accountId);
    public function sendEmail($emailData);
}
