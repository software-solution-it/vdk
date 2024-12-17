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
            if (!array_key_exists($field, $data) || is_null($data[$field])) {
                $missingFields[] = $field;
            }
        }
    
        return $missingFields;
    }
    

    public function createEmailAccount($data) {
        $requiredFields = ['user_id', 'email', 'provider_id', 'password', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = $this->validateFields($data, $requiredFields);
    
        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
        }
    
        $user = $this->userModel->getUserById($data['user_id']);
        if (!$user) {
            return ['status' => false, 'message' => 'User does not exist']; 
        }
    

        $provider = $this->providerModel->getById($data['provider_id']);
        if (!$provider) {
            return ['status' => false, 'message' => 'Provider does not exist']; 
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
    
            return ['status' => true, 'message' => 'Email account created successfully.', 'data' => $createdEmailAccount];
        }
    
        return ['status' => false, 'message' => 'Failed to create email account'];
    }
    
    
    

    public function updateEmailAccount($id, $data) {
        $existingEmailAccount = $this->emailAccountModel->getById($id);
        if (!$existingEmailAccount) {
            return ['status' => false, 'message' => 'Email account not found'];
        }
    
        $requiredFields = ['email', 'provider_id', 'oauth_token', 'refresh_token', 'client_id', 'client_secret', 'is_basic'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            return !isset($data[$field]) || $data[$field] === '';
        });
    
        if (!empty($missingFields)) {
            return ['status' => false, 'message' => 'Missing fields: ' . implode(', ', $missingFields)];
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
            return $this->emailAccountModel->getById($id);
        }
    
        return ['status' => false, 'message' => 'Failed to update email account'];
    }
    
    
    

    public function deleteEmailAccount($id) {
        $emailAccount = $this->emailAccountModel->getById($id);
        
        if (!$emailAccount) {
            return ['status' => false, 'message' => 'Email account not found'];
        }
    
        $emails = $this->emailModel->getEmailsByUserId($id);
        foreach ($emails as $email) {
            
            $this->emailAttachmentModel->deleteAttachmentsByEmailId($email['id']);

            $this->emailModel->deleteEmail($email['email_id']);
            
        }

        $this->emailFolderModel->deleteFoldersByEmailAccountId($id);
    
        $deleted = $this->emailAccountModel->delete($id);
    
        if ($deleted) {
            return ['status' => true, 'message' => 'Email account and all associated data deleted successfully'];
        }
        
        return ['status' => false, 'message' => 'Failed to delete email account and associated data'];
    }
    

    public function getEmailAccountByUserId($id) {
        $result = $this->emailAccountModel->getEmailAccountByUserId($id);

        return $result; 
    }

    public function getEmailAccountById($id) {
        $result = $this->emailAccountModel->getEmailAccountById($id);

        return $result; 
    }
}
