<?php
namespace App\Services;

use App\Models\Provider;

class ProviderService {
    private $providerModel;

    public function __construct($db) {
        $this->providerModel = new Provider($db);
    }

    public function createProvider($data) {
        // Validação de campos
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return [
                'status' => false, 
                'message' => 'Validation failed: Missing fields: ' . implode(', ', $missingFields),
                'error_code' => 'MISSING_FIELDS'
            ];
        }
    
        try {
            $created = $this->providerModel->create(
                $data['name'],
                $data['smtp_host'],
                $data['smtp_port'],
                $data['imap_host'],
                $data['imap_port'],
                $data['encryption']
            );
    
            if ($created) {
                $provider = $this->providerModel->getById($created);
                return ['status' => true, 'message' => 'Provider created successfully', 'data' => $provider];
            }
    
            return [
                'status' => false, 
                'message' => 'Failed to create provider. Please try again later.',
                'error_code' => 'CREATE_FAILED'
            ];
        } catch (\Exception $e) {
            // Tratamento de exceção
            return [
                'status' => false,
                'message' => 'Provider with this name already exists.',
                'error_code' => 'CREATE_FAILED',
                'details' => $e->getMessage()
            ];
        }
    }
    
    public function updateProvider($id, $data) {
        // Verifica se o registro existe
        $existingProvider = $this->providerModel->getById($id);
        if (!$existingProvider) {
            return [
                'status' => false,
                'message' => 'Provider not found',
                'error_code' => 'PROVIDER_NOT_FOUND'
            ];
        }
    
        // Valida campos obrigatórios
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return [
                'status' => false,
                'message' => 'Missing fields: ' . implode(', ', $missingFields),
                'error_code' => 'MISSING_FIELDS'
            ];
        }
    
        // Verifica duplicidade do nome (se já pertence a outro registro)
        $providerWithSameName = $this->providerModel->getByName($data['name']);
        if ($providerWithSameName && $providerWithSameName['id'] != $id) {
            return [
                'status' => false,
                'message' => 'Provider with this name already exists.',
                'error_code' => 'DUPLICATE_NAME'
            ];
        }
    
        // Tenta atualizar o registro
        try {
            $updated = $this->providerModel->update(
                $id,
                $data['name'],
                $data['smtp_host'],
                $data['smtp_port'],
                $data['imap_host'],
                $data['imap_port'],
                $data['encryption']
            );
    
            if ($updated) {
                $provider = $this->providerModel->getById($id);
                return [
                    'status' => true,
                    'message' => 'Provider updated successfully',
                    'data' => $provider
                ];
            }
    
            return [
                'status' => false,
                'message' => 'Failed to update provider. Please try again later.',
                'error_code' => 'UPDATE_FAILED'
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Provider with this name already exists.',
                'error_code' => 'UPDATE_FAILED',
                'details' => $e->getMessage()
            ];
        }
    }
    

    public function deleteProvider($id) {
        // Verifica se o registro existe
        $existingProvider = $this->providerModel->getById($id);
        if (!$existingProvider) {
            return ['status' => false, 'message' => 'Provider not found'];
        }

        // Deleta o registro
        $deleted = $this->providerModel->delete($id);

        if ($deleted) {
            return ['status' => true, 'message' => 'Provider deleted successfully'];
        }

        return ['status' => false, 'message' => 'Failed to delete provider'];
    }

    public function getProviderById($id) {
        try {
            $provider = $this->providerModel->getById($id);

            if ($provider) {
                return $provider;
            }

            return null;
        } catch (\Exception $e) {
            throw new \Exception("Error retrieving provider: " . $e->getMessage());
        }
    }

    public function getAllProviders() {
        return $this->providerModel->getAll();
    }

    private function validateFields($data, $requiredFields) {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }
}
