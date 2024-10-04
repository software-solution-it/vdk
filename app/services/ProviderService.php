<?php

include_once __DIR__ . '/../models/Provider.php';

class ProviderService {
    private $providerModel;

    public function __construct($db) {
        $this->providerModel = new Provider($db);
    }

    public function createProvider($data) {
        $requiredFields = ['name', 'smtp_host', 'smtp_port', 'imap_host', 'imap_port', 'encryption'];
        $missingFields = $this->validateFields($data, $requiredFields);

        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }

        $created = $this->providerModel->create(
            $data['name'],
            $data['smtp_host'],
            $data['smtp_port'],
            $data['imap_host'],
            $data['imap_port'],
            $data['encryption']
        );

        if ($created) {
            return ['status' => true, 'message' => 'Provider created successfully'];
        }

        return ['status' => false, 'message' => 'Failed to create provider'];
    }

    public function updateProvider($id, $data) {
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
            return ['status' => true, 'message' => 'Provider updated successfully'];
        }

        return ['status' => false, 'message' => 'Failed to update provider'];
    }


    public function deleteProvider($id) {
        $deleted = $this->providerModel->delete($id);

        if ($deleted) {
            return ['status' => true, 'message' => 'Provider deleted successfully'];
        }

        return ['status' => false, 'message' => 'Failed to delete provider'];
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
