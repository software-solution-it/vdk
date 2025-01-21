<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;

class S3Service {
    private $s3Client;
    private $bucketName;

    public function __construct() {
        // Debug das variáveis de ambiente
        error_log("AWS Environment Variables:");
        error_log("AWS_ACCESS_KEY_ID: " . getenv('AWS_ACCESS_KEY_ID'));
        error_log("AWS_SECRET_ACCESS_KEY: " . (getenv('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'NOT SET'));
        error_log("AWS_DEFAULT_REGION: " . getenv('AWS_DEFAULT_REGION'));
        error_log("AWS_BUCKET: " . getenv('AWS_BUCKET'));
        error_log("AWS_ENDPOINT: " . getenv('AWS_ENDPOINT'));

        $config = [
            'version'     => 'latest',
            'region'      => 'sa-east-1',
            'credentials' => [
                'key'    => 'AKIAU72LGEZVJUF3CXXE',
                'secret' => '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',
            ],
            'endpoint'    => 'https://s3.sa-east-1.amazonaws.com'
        ];

        try {
            $this->s3Client = new S3Client($config);
            $this->bucketName = 'vdkmail';
            error_log("S3 client initialized successfully");
        } catch (Exception $e) {
            error_log("Error initializing S3 client: " . $e->getMessage());
            throw $e; 
        }
    }

    public function generatePresignedUrl($key) {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);

            $request = $this->s3Client->createPresignedRequest($command, '+1 hour');
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log("Error generating presigned URL: " . $e->getMessage());
            throw new Exception("Erro ao gerar URL pré-assinada: " . $e->getMessage());
        }
    }
} 