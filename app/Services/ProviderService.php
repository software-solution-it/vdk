<?php
namespace App\Services;

use App\Models\Provider;
use App\Models\EmailAccount;

class ProviderService {
    private $providerModel;
    private $emailAccountModel; 

    public function __construct($db) {
        $this->providerModel = new Provider($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function createProvider($data) {
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return [
                'status' => false, 
                'message' => 'Validation failed: Missing fields: ' . implode(', ', $missingFields),
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
                return [
                    'status' => true,
                    'message' => 'Provider created successfully',
                    'data' => $provider 
                ];
            }
    
            return [
                'status' => false, 
                'message' => 'Failed to create provider. Please try again later.',
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Provider with this name already exists.',
                'details' => $e->getMessage()
            ];
        }
    }
    
    public function updateProvider($id, $data) {
        try {
            $existingProvider = $this->providerModel->getById($id);
            if (!$existingProvider) {
                return ['status' => false, 'message' => 'Provider not found'];
            }

            $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
            $missingFields = $this->validateFields($data, $requiredFields);

            if (!empty($missingFields)) {
                return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
            }

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
                return ['status' => true, 'message' => 'Provider updated successfully', 'data' => $provider];
            }
            
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Provider with this name already exists.',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProvider($id) {
        $existingProvider = $this->providerModel->getById($id);
        if (!$existingProvider) {
            return ['status' => false, 'message' => 'Provider not found', 'http_code' => 404];
        }

        $associatedEmailAccount = $this->emailAccountModel->getByProviderId($id);
        if ($associatedEmailAccount) {
            return [
                'status' => false,
                'message' => 'Cannot delete provider. Email account(s) are associated with this provider.',
                'http_code' => 400
            ];
        }

        $deleted = $this->providerModel->delete($id);
        if ($deleted) {
            return ['status' => true, 'message' => 'Provider deleted successfully', 'http_code' => 200];
        }

        return ['status' => false, 'message' => 'Failed to delete provider', 'http_code' => 500];
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
