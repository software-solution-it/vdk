<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;

class S3Service {
    private $s3Client;

    public function __construct() {
        $this->s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => getenv('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);
    }

    public function generatePresignedUrl($key) {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => getenv('AWS_BUCKET'),
                'Key'    => $key
            ]);

            $request = $this->s3Client->createPresignedRequest($command, '+1 hour');
            return (string) $request->getUri();
        } catch (Exception $e) {
            throw new Exception("Erro ao gerar URL pré-assinada: " . $e->getMessage());
        }
    }
} 