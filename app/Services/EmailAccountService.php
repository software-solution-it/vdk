<?php
namespace App\Services;

use App\Models\EmailAccount;
use App\Helpers\EncryptionHelper;

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
            $data['refresh_token'] ?? null,
            $data['client_id'] ?? null,
            $data['client_secret'] ?? null
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
            $data['refresh_token'] ?? null,
            $data['client_id'] ?? null,
            $data['client_secret'] ?? null
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

    public function getEmailAccountByUserId($id) {
        $result = $this->emailAccountModel->getEmailAccountByUserId($id);

        return $result; 
    }
}
