<?php

include_once __DIR__ . '/../models/Campaign.php';
include_once __DIR__ . '/../config/database.php';

class CampaignController {
    private $campaign;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->campaign = new Campaign($db);

    }

    public function createCampaign() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['email_account_id'], $data['name'], $data['priority'])) {
            if ($this->campaign->create($data['email_account_id'], $data['name'], $data['priority'])) {
                echo json_encode(['message' => 'Campanha criada com sucesso']);
            } else {
                echo json_encode(['message' => 'Erro ao criar a campanha']);
            }
        } else {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
        }
    }

    public function readAllCampaigns() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['user_id'], $data['email_account_id'])) {
            $result = $this->campaign->readAll($data['user_id'], $data['email_account_id']);
            echo json_encode($result);
        } else {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
        }
    }

    public function readCampaignById() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id'])) {
            $result = $this->campaign->readById($data['id']);
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode(['message' => 'Campanha não encontrada']);
            }
        } else {
            echo json_encode(['message' => 'ID não fornecido']);
        }
    }

    public function updateCampaign() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id'], $data['name'], $data['priority'])) {
            if ($this->campaign->update($data['id'], $data['name'], $data['priority'])) {
                echo json_encode(['message' => 'Campanha atualizada com sucesso']);
            } else {
                echo json_encode(['message' => 'Erro ao atualizar a campanha']);
            }
        } else {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
        }
    }

    public function deleteCampaign() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id'])) {
            if ($this->campaign->delete($data['id'])) {
                echo json_encode(['message' => 'Campanha excluída com sucesso']);
            } else {
                echo json_encode(['message' => 'Erro ao excluir a campanha']);
            }
        } else {
            echo json_encode(['message' => 'Parâmetros insuficientes']);
        }
    }
}
