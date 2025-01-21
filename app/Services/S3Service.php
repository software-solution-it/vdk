<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;

class S3Service {
    private $s3Client;
    private $bucketName;

    public function __construct() {
        $config = [
            'version'     => 'latest',
            'region'      => getenv('AWS_DEFAULT_REGION') ?: 'sa-east-1',
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            ]
        ];

        try {
            $this->s3Client = new S3Client($config);
            // Definir um valor padrão caso a variável de ambiente não esteja definida
            $this->bucketName = getenv('AWS_BUCKET_NAME') ?: 'vdkmail';
            
            if (empty($this->bucketName)) {
                throw new Exception("AWS bucket name is not configured");
            }
        } catch (Exception $e) {
            throw new Exception("Error initializing S3 client: " . $e->getMessage());
        }
    }

    public function getBucketName() {
        return $this->bucketName;
    }

    public function generatePresignedUrl($key) {
        try {
            if (empty($this->bucketName)) {
                throw new Exception("AWS bucket name is not configured");
            }

            if (empty($key)) {
                throw new Exception("S3 key cannot be empty");
            }

            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, '+1 hour');
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log("S3 Error: " . $e->getMessage());
            return null;
        }
    }
} 