<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;

class S3Service {
    private $s3Client;
    private $bucketName;

    public function __construct() {
        // Carrega as credenciais
        $accessKey = getenv('AWS_ACCESS_KEY_ID');
        $secretKey = getenv('AWS_SECRET_ACCESS_KEY');
        $region = getenv('AWS_DEFAULT_REGION') ?: 'sa-east-1';
        $this->bucketName = getenv('AWS_BUCKET') ?: 'vdkmail';

        // Valida as credenciais antes de criar o cliente
        if (empty($accessKey) || empty($secretKey)) {
            throw new Exception("AWS credentials are not properly configured");
        }

        if (empty($this->bucketName)) {
            throw new Exception("AWS bucket name is not configured");
        }

        try {
            $config = [
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => [
                    'key'    => $accessKey,
                    'secret' => $secretKey,
                ],
                'signature_version' => 'v4'
            ];

            $this->s3Client = new S3Client($config);

        } catch (Exception $e) {
            throw new Exception("Error initializing S3 client: " . $e->getMessage());
        }
    }

    public function getBucketName() {
        return $this->bucketName;
    }

    public function generatePresignedUrl($key) {
        try {
            if (empty($key)) {
                throw new Exception("S3 key cannot be empty");
            }

            // Verifica se o objeto existe antes de gerar a URL
            if (!$this->s3Client->doesObjectExist($this->bucketName, $key)) {
                error_log("S3 object does not exist: {$key}");
                return null;
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