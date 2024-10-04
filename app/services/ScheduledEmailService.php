<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/ScheduledEmails.php';
require_once __DIR__ . '/../services/EmailService.php'; 

class ScheduledEmailService {
    private $db;
    private $emailService;

    public function __construct($db) {
        $this->db = $db;
        $this->emailService = new EmailService();
    }


    public function sendAllPendingEmails($campaign_id) {
        if (!$this->campaignExists($campaign_id)) {
            error_log("Campanha com ID $campaign_id não existe.");
            return false; 
        }

        $query = "
            SELECT se.id, se.name, se.recipient_email, se.subject, se.html_template, se.campaign_id, c.email_account_id, ea.user_id
            FROM scheduled_emails se
            INNER JOIN campaigns c ON se.campaign_id = c.id
            INNER JOIN email_accounts ea ON c.email_account_id = ea.id
            WHERE se.campaign_id = :campaign_id AND se.status = 'pending'
            ORDER BY c.priority ASC, se.scheduled_at ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':campaign_id', $campaign_id);
        $stmt->execute();

        $scheduledEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($scheduledEmails)) {
            error_log("Nenhum e-mail agendado encontrado para a campanha com ID $campaign_id.");
            return false; 
        }

        foreach ($scheduledEmails as $email) {
            error_log("Scheduled Email Data: " . json_encode($email));


            $result = $this->emailService->sendEmailWithTemplate(
                $email['user_id'],
                $email['email_account_id'],
                $email['name'],
                $email['recipient_email'],
                $email['subject'],
                $email['html_template']
            );

            if ($result) {
                $this->updateEmailStatus($email['id'], 'sent'); 
            } else {
                $this->updateEmailStatus($email['id'], 'failed');
            }
        }
        return true; 
    }

    public function campaignExists($campaign_id) {
        $query = "SELECT id FROM campaigns WHERE id = :campaign_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':campaign_id', $campaign_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? true : false;
    }

    private function updateEmailStatus($email_id, $status) {
        $query = "UPDATE scheduled_emails SET status = :status WHERE id = :email_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':email_id', $email_id);
        return $stmt->execute();
    }

}
