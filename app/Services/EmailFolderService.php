<?php

namespace App\Services;

use App\Models\EmailFolder;
use Aws\S3\S3Client;
use Exception;

class EmailFolderService {
    private $emailFolderModel;
    private $s3Client;
    private $bucketName;

    public function __construct($db) {
        $this->emailFolderModel = new EmailFolder($db);
        
        try {
            if (getenv('AWS_ACCESS_KEY_ID') && getenv('AWS_SECRET_ACCESS_KEY') && getenv('AWS_REGION')) {
                $this->s3Client = new S3Client([
                    'version' => 'latest',
                    'region'  => getenv('AWS_REGION'),
                    'credentials' => [
                        'key'    => getenv('AWS_ACCESS_KEY_ID'),
                        'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                    ]
                ]);
                $this->bucketName = getenv('AWS_BUCKET') ?: 'vdkmail';
            }
        } catch (Exception $e) {
            error_log("Error initializing S3 client: " . $e->getMessage());
        }
    }

    public function getFoldersByEmailId($email_id) {
        $folders = $this->emailFolderModel->getFoldersNameByEmailAccountId($email_id);
        
        // Adiciona URLs pré-assinadas do S3 se houver anexos
        if ($this->s3Client && !empty($folders)) {
            foreach ($folders as &$folder) {
                if ($folder['s3_attachment_count'] > 0) {
                    $folder['has_s3_attachments'] = true;
                    $folder['s3_enabled'] = true;
                } else {
                    $folder['has_s3_attachments'] = false;
                    $folder['s3_enabled'] = false;
                }
            }
        }

        return $folders;
    }
}
