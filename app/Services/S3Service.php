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
            ]
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
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);
            
            return (string) $this->s3Client->createPresignedRequest($cmd, '+1 hour')->getUri();
        } catch (Exception $e) {
            error_log("S3 Error: " . $e->getMessage());
            return null;
        }
    }
} 