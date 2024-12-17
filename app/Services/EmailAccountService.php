<?php
namespace App\Services;

use App\Models\EmailAccount;
use App\Helpers\EncryptionHelper;
use App\Models\EmailFolder;
use App\Models\Email;
use App\Models\User;
use App\Models\Provider;
use App\Models\EmailAttachment;  

class EmailAccountService {
    private $emailAccountModel;
    private $emailModel;
    private $emailFolderModel;
    private $emailAttachmentModel; 
    private $userModel;
    private $providerModel;
    
    public function __construct($db) {
        $this->emailAccountModel = new EmailAccount($db);
        $this->emailModel = new Email($db);
        $this->emailFolderModel = new EmailFolder($db);
        $this->emailAttachmentModel = new EmailAttachment($db);
        $this->userModel = new User($db);
        $this->providerModel = new Provider($db);
    }
    public function validateFields($data, $requiredFields) {
        $missingFields = [];
    
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || (is_null($data[$field]) && $data[$field] !== "")) {
                $missingFields[] = $field;
            }
        }
    
        return $missingFields;
    }
    
    

    public function createEmailAccount($data) {
        $requiredFields = ['user_id', 'email', 'provider_id', 'password', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return [
                'status' => false,
                'message' => 'Missing fields: ' . implode(', ', $missingFields),
                'data' => null,
                'http_code' => 400
            ];
        }
    
        $user = $this->userModel->getUserById($data['user_id']);
        if (!$user) {
            return [
                'status' => false,
                'message' => 'User does not exist',
                'data' => null,
                'http_code' => 400
            ]; 
        }
    
        $provider = $this->providerModel->getById($data['provider_id']);
        if (!$provider) {
            return [
                'status' => false,
                'message' => 'Provider does not exist',
                'data' => null,
                'http_code' => 400
            ]; 
        }
    
        $existingEmailAccount = $this->emailAccountModel->getByEmailAndProvider($data['email'], $data['provider_id']);
        if ($existingEmailAccount) {
            return [
                'status' => false,
                'message' => 'Email account already exists for this provider',
                'data' => null,
                'http_code' => 400
            ]; 
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
            $createdEmailAccount = $this->emailAccountModel->getById($emailAccountId);
            return [
                'status' => true,
                'message' => 'Email account created successfully.',
                'data' => $createdEmailAccount,
                'http_code' => 201
            ];
        }
    
        return [
            'status' => false,
            'message' => 'Failed to create email account',
            'data' => null,
            'http_code' => 400
        ];
    }

    public function updateEmailAccount($id, $data) {
        $existingEmailAccount = $this->emailAccountModel->getById($id);
        if (!$existingEmailAccount) {
            return [
                'status' => false,
                'message' => 'Email account not found',
                'data' => null,
                'http_code' => 400
            ];
        }
    
        $requiredFields = ['email', 'provider_id', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            return !array_key_exists($field, $data) || ($data[$field] === '' && !is_null($data[$field]));
        });
    
        if (!empty($missingFields)) {
            return [
                'status' => false,
                'message' => 'Missing fields: ' . implode(', ', $missingFields),
                'data' => null,
                'http_code' => 400
            ];
        }
    
        $provider = $this->providerModel->getById($data['provider_id']);
        if (!$provider) {
            return [
                'status' => false,
                'message' => 'Provider does not exist',
                'data' => null,
                'http_code' => 400
            ]; 
        }
    
        $user = $this->userModel->getUserById($data['user_id']);
        if (!$user) {
            return [
                'status' => false,
                'message' => 'Invalid user_id: user does not exist',
                'data' => null,
                'http_code' => 400
            ];
        }
    
        $encryptedPassword = isset($data['password']) && $data['password'] !== '' 
            ? EncryptionHelper::encrypt($data['password']) 
            : $existingEmailAccount['password'];
    
        $is_basic = $data['is_basic'] ?? $existingEmailAccount['is_basic'];
    
        $updated = $this->emailAccountModel->update(
            $id,
            $data['email'] ?? $existingEmailAccount['email'],
            $data['provider_id'] ?? $existingEmailAccount['provider_id'],
            $encryptedPassword,
            $data['oauth_token'] ?? $existingEmailAccount['oauth_token'],
            $data['refresh_token'] ?? $existingEmailAccount['refresh_token'],
            $data['client_id'] ?? $existingEmailAccount['client_id'],
            $data['client_secret'] ?? $existingEmailAccount['client_secret'],
            $is_basic
        );
    
        if ($updated) {
            return [
                'status' => true,
                'message' => 'Email account updated successfully.',
                'data' => $this->emailAccountModel->getById($id),
                'http_code' => 200
            ];
        }
    
        return [
            'status' => false,
            'message' => 'Failed to update email account',
            'data' => null,
            'http_code' => 400
        ];
    }
    
    
    public function deleteEmailAccount($id) {
        $emailAccount = $this->emailAccountModel->getById($id);
        
        if (!$emailAccount) {
            return ['status' => false, 'message' => 'Email account not found', 'data' => null, 'http_code' => 400];
        }
    
        $emails = $this->emailModel->getEmailsByUserId($id);
        foreach ($emails as $email) {
            $this->emailAttachmentModel->deleteAttachmentsByEmailId($email['id']);
            $this->emailModel->deleteEmail($email['email_id']);
        }

        $this->emailFolderModel->deleteFoldersByEmailAccountId($id);
    
        $deleted = $this->emailAccountModel->delete($id);
    
        if ($deleted) {
            return ['status' => true, 'message' => 'Email account and all associated data deleted successfully', 'data' => null, 'http_code' => 200];
        }
        
        return ['status' => false, 'message' => 'Failed to delete email account and associated data', 'data' => null, 'http_code' => 400];
    }
    
    public function getEmailAccountByUserId($id) {
        return $this->emailAccountModel->getEmailAccountByUserId($id);
    }

    public function getEmailAccountById($id) {
        return $this->emailAccountModel->getEmailAccountById($id);
    }
}