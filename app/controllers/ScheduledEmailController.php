<?php

require_once __DIR__ . '/../models/ScheduledEmails.php';
require_once __DIR__ . '/../services/ScheduledEmailService.php';
require_once __DIR__ . '/../config/database.php';

class ScheduledEmailController {
    private $scheduledEmails;
    private $ScheduledEmailervice;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->scheduledEmails = new ScheduledEmail($db);
        $this->ScheduledEmailervice = new ScheduledEmailService($db);
    }

    

    public function sendAllEmails() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['campaign_id'])) {
            echo json_encode(['message' => 'ID da campanha não fornecido']);
            return;
        }

        if (!$this->ScheduledEmailervice->campaignExists($data['campaign_id'])) {
            echo json_encode(['message' => 'Campanha não encontrada']);
            return;
        }

        if (!$this->ScheduledEmailervice->sendAllPendingEmails($data['campaign_id'])) {
            echo json_encode(['message' => 'Nenhum e-mail agendado para envio ou erro ao processar']);
            return;
        }

        echo json_encode(['message' => 'E-mails processados e enviados']);
    }

    public function createScheduledEmail() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['campaign_id'], $data['name'], $data['recipient_email'], $data['subject'], $data['html_template'], $data['scheduled_at'])) {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
            return;
        }

        if (!filter_var($data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['message' => 'E-mail inválido']);
            return;
        }

        if (!$this->ScheduledEmailervice->campaignExists($data['campaign_id'])) {
            echo json_encode(['message' => 'Campanha não encontrada']);
            return;
        }

        if ($this->scheduledEmails->create($data['campaign_id'], $data['name'], $data['recipient_email'], $data['subject'], $data['html_template'], $data['scheduled_at'])) {
            echo json_encode(['message' => 'E-mail agendado com sucesso']);
        } else {
            echo json_encode(['message' => 'Erro ao agendar o e-mail']);
        }
    }

    public function readAllScheduledEmail() {
        $result = $this->scheduledEmails->readAll();
        echo json_encode($result);
    }

    public function readScheduledEmailById() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            echo json_encode(['message' => 'ID não fornecido']);
            return;
        }

        $result = $this->scheduledEmails->readById($data['id']);
        if ($result) {
            echo json_encode($result);
        } else {
            echo json_encode(['message' => 'E-mail não encontrado']);
        }
    }

    public function updateScheduledEmail() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'], $data['name'], $data['recipient_email'], $data['subject'], $data['html_template'], $data['scheduled_at'])) {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
            return;
        }

        if (!filter_var($data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['message' => 'E-mail inválido']);
            return;
        }

        if ($this->scheduledEmails->update($data['id'], $data['name'], $data['recipient_email'], $data['subject'], $data['html_template'], $data['scheduled_at'])) {
            echo json_encode(['message' => 'E-mail agendado atualizado com sucesso']);
        } else {
            echo json_encode(['message' => 'Erro ao atualizar o e-mail agendado']);
        }
    }

    public function deleteScheduledEmail() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            echo json_encode(['message' => 'ID não fornecido']);
            return;
        }

        if ($this->scheduledEmails->delete($data['id'])) {
            echo json_encode(['message' => 'E-mail agendado excluído com sucesso']);
        } else {
            echo json_encode(['message' => 'Erro ao excluir o e-mail agendado']);
        }
    }
}
