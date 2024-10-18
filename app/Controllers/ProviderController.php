<?php
namespace App\Controllers;

use App\Services\ProviderService;
use App\Config\Database;

class ProviderController {
    private $providerService;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->providerService = new ProviderService($db);
    }

    public function createProvider() {
        $data = json_decode(file_get_contents('php://input'), true);
        $response = $this->providerService->createProvider($data);
        echo json_encode($response);
    }

    public function updateProvider($id) {
        $data = json_decode(file_get_contents('php://input'), true);
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
