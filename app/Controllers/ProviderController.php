<?php

namespace App\Controllers;

use App\Services\ProviderService;
use App\Config\Database;
use App\Controllers\ErrorLogController;

class ProviderController {
    private $providerService;
    private $errorLogController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->providerService = new ProviderService($db);
        $this->errorLogController = new ErrorLogController();
    }

    public function createProvider() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['host'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Name and host are required.']);
            return;
        }

        $response = $this->providerService->createProvider($data);
        echo json_encode($response);
    }

    public function updateProvider($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['host'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Name and host are required.']);
            return;
        }

        $response = $this->providerService->updateProvider($id, $data);
        echo json_encode($response);
    }

    public function deleteProvider($id) {
        $response = $this->providerService->deleteProvider($id);
        echo json_encode($response);
    }
    
    public function getAllProviders() {
        $response = $this->providerService->getAllProviders();
        echo json_encode($response);
    }
}
