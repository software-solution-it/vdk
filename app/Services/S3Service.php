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
        } catch (Exception $e) {
            throw new Exception("Error initializing S3 client: " . $e->getMessage());
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
            throw new Exception("Error generating presigned URL: " . $e->getMessage());
        }
    }
} 