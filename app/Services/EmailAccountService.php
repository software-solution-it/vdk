<?php
namespace App\Services;

use App\Models\EmailAccount;
use App\Helpers\EncryptionHelper;

class EmailAccountService {
    private $emailAccountModel;

    public function __construct($db) {
        $this->emailAccountModel = new EmailAccount($db);
    }


    public function validateFields($data, $requiredFields) {
        $missingFields = [];
    
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || is_null($data[$field])) {
                $missingFields[] = $field;
            }
        }
    
        return $missingFields;
    }
    

    public function createEmailAccount($data) {
        $requiredFields = ['user_id','email', 'provider_id', 'password', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }
    
        $encryptedPassword = EncryptionHelper::encrypt($data['password']);
    
        $emailAccountId = $this->emailAccountModel->create(
            $data['user_id'],
            $data['email'],
            $data['provider_id'],
            $encryptedPassword,
            $data['oauth_token'] ?? null,
            $data['refresh_token'] ?? null,
            $data['client_id'] ?? null,
            $data['client_secret'] ?? null,
            $data['is_basic'] ?? true
        );
    
        if ($emailAccountId) {
            return ['status' => true, 'message' => 'Email account created successfully', 'email_account_id' => $emailAccountId];
        }
    
        return ['status' => false, 'message' => 'Failed to create email account'];
    }

    public function updateEmailAccount($id, $data) {
        $requiredFields = ['email', 'provider_id', 'password', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }
    
        $encryptedPassword = EncryptionHelper::encrypt($data['password']);
    
        $is_basic = $data['is_basic'] ?? null;
    
        $updated = $this->emailAccountModel->update( 
            $id,
            $data['email'],
            $data['provider_id'],
            $encryptedPassword ?? null,
            $data['oauth_token'] ?? null,
            $data['refresh_token'] ?? null,
            $data['client_id'] ?? null,
            $data['client_secret'] ?? null,
            $is_basic ?? true
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
