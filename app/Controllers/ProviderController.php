<?php

namespace App\Controllers;

use App\Services\ProviderService;
use App\Config\Database;
use App\Controllers\ErrorLogController;
use Exception;

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
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
    
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = [];
    
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
    
        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }
    
        $response = $this->providerService->createProvider($data);
        http_response_code($response['status'] ? 201 : 500);
        echo json_encode([
            'Status' => $response['status'] ? 'Success' : 'Error',
            'Message' => $response['message'],
            'Data' => $response['status'] ? $response['data'] : null
        ]);
    }
    
    public function updateProvider($id) {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
    
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = [];
    
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
    
        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Missing fields: ' . implode(', ', $missingFields),
                'Data' => null
            ]);
            return;
        }
    
        $response = $this->providerService->updateProvider($id, $data);
        http_response_code($response['status'] ? 200 : 500);
        echo json_encode([
            'Status' => $response['status'] ? 'Success' : 'Error',
            'Message' => $response['message'],
            'Data' => $response['status'] ? $response['data'] : null
        ]);
    }
    
    
    public function deleteProvider($id) {
        header('Content-Type: application/json');
        $response = $this->providerService->deleteProvider($id);
        http_response_code($response['status'] ? 200 : 500);
        echo json_encode([
            'Status' => $response['status'] ? 'Success' : 'Error',
            'Message' => $response['message'],
            'Data' => null
        ]);
    }
    
    public function getAllProviders() {
        header('Content-Type: application/json');
        $response = $this->providerService->getAllProviders();
        http_response_code(200);
        echo json_encode([
            'Status' => 'Success',
            'Message' => 'Providers retrieved successfully.',
            'Data' => $response
        ]);
    }

    public function getProviderById($id) {
        header('Content-Type: application/json');
    
        try {
            if (empty($id)) {
                http_response_code(400);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Provider ID is required',
                    'Data' => null
                ]);
                return;
            }
    
            $response = $this->providerService->getProviderById($id);
    
            if ($response) {
                http_response_code(200);
                echo json_encode([
                    'Status' => 'Success',
                    'Message' => 'Provider retrieved successfully',
                    'Data' => $response
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'Status' => 'Error',
                    'Message' => 'Provider not found',
                    'Data' => null
                ]);
            } 
        } catch (Exception $e) {
            $this->errorLogController->logError(
                "Error retrieving provider: " . $e->getMessage(),
                __FILE__,
                __LINE__,
                null
            );
            http_response_code(500);
            echo json_encode([
                'Status' => 'Error',
                'Message' => 'Internal server error',
                'Data' => null
            ]);
        }
    }
}
