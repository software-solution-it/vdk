<?php

include_once __DIR__ . '/../models/EmailAccount.php';
include_once __DIR__ . '/../helpers/EncryptionHelper.php';

class EmailAccountService {
    private $emailAccountModel;

    public function __construct($db) {
        $this->emailAccountModel = new EmailAccount($db);
    }


    private function validateFields($data, $requiredFields) {
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
        return $missingFields;
    }


    public function createEmailAccount($data) {
        $requiredFields = ['user_id', 'email', 'provider_id'];
        $missingFields = $this->validateFields($data, $requiredFields);

        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }

        $encryptedPassword = EncryptionHelper::encrypt($data['password']);


        $emailAccountId = $this->emailAccountModel->create(
            $data['user_id'],
            $data['email'],
            $data['provider_id'],
            $encryptedPassword ?? null,
            $data['oauth_token'] ?? null,
            $data['refresh_token'] ?? null
        );

        if ($emailAccountId) {
            return ['status' => true, 'message' => 'Email account created successfully', 'email_account_id' => $emailAccountId];
        }

        return ['status' => false, 'message' => 'Failed to create email account'];
    }


    public function updateEmailAccount($id, $data) {
        $requiredFields = ['email', 'provider_id'];
        $missingFields = $this->validateFields($data, $requiredFields);

        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }

        $encryptedPassword = EncryptionHelper::encrypt($data['password']);

        $updated = $this->emailAccountModel->update(
            $id,
            $data['email'],
            $data['provider_id'],
            $encryptedPassword ?? null,
            $data['oauth_token'] ?? null,
            $data['refresh_token'] ?? null
        );

        if ($updated) {
            return ['status' => true, 'message' => 'Email account updated successfully'];
        }

        return ['status' => false, 'message' => 'Failed to update email account'];
    }


    public function deleteEmailAccount($id) {
        $deleted = $this->emailAccountModel->delete($id);

        if ($deleted) {
            return ['status' => true, 'message' => 'Email account deleted successfully'];
        }

        return ['status' => false, 'message' => 'Failed to delete email account'];
    }

    public function getEmailAccountById($id) {
        $account = $this->emailAccountModel->getById($id);
        if ($account) {
            return ['status' => true, 'data' => $account];
        }
        return ['status' => false, 'message' => 'Email account not found'];
    }
}
