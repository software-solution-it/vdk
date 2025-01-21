<?php

namespace App\Services;

use Aws\S3\S3Client;
use Exception;
use App\Models\AwsCredential;

class S3Service {
    private $s3Client;
    private $bucketName;

    public function __construct($db) {
        try {
            $awsCredential = new AwsCredential($db);
            $credentials = $awsCredential->getCredentials();
            
            if (!$credentials) {
                throw new Exception("Credenciais AWS não encontradas no banco de dados");
            }

            $this->bucketName = $credentials['bucket'];
            
            $config = [
                'version'     => 'latest',
                'region'      => $credentials['region'],
                'credentials' => [
                    'key'    => $credentials['access_key_id'],
                    'secret' => $credentials['secret_access_key'],
                ],
                'signature_version' => 'v4'
            ];

            if (!empty($credentials['endpoint'])) {
                $config['endpoint'] = $credentials['endpoint'];
            }

            $this->s3Client = new S3Client($config);
            error_log("Cliente S3 inicializado com sucesso");

        } catch (Exception $e) {
            error_log("Erro ao inicializar S3 client: " . $e->getMessage());
            throw new Exception("Erro ao inicializar S3 client: " . $e->getMessage());
        }
    }

    public function getBucketName() {
        return $this->bucketName;
    }

    public function generatePresignedUrl($key) {
        try {
            if (empty($key)) {
                error_log("S3 Error: Empty key provided");
                return null;
            }

            // Limpa e normaliza o caminho
            $cleanKey = $this->normalizeS3Key($key);
            
            // Extrai o hash e o nome do arquivo
            $parts = explode('/', $cleanKey);
            $filename = end($parts);
            $hash = '';
            
            // Procura pelo hash no caminho (geralmente é uma string longa hexadecimal)
            foreach ($parts as $part) {
                if (strlen($part) >= 64) { // Hash SHA-256 tem 64 caracteres
                    $hash = $part;
                    break;
                }
            }

            // Se encontrou um hash, constrói o caminho correto
            if ($hash) {
                $correctPath = "attachments/{$hash}/{$filename}";
                error_log("Tentando acessar com caminho correto: " . $correctPath);

                if ($this->s3Client->doesObjectExist($this->bucketName, $correctPath)) {
                    return $this->createPresignedUrl($correctPath);
                }
            }

            // Se não funcionou com o hash encontrado, tenta o caminho original limpo
            if ($this->s3Client->doesObjectExist($this->bucketName, $cleanKey)) {
                return $this->createPresignedUrl($cleanKey);
            }

            error_log("S3 object does not exist in attempted paths: " . 
                     "\nOriginal: " . $key .
                     "\nCleaned: " . $cleanKey .
                     ($hash ? "\nConstructed: " . $correctPath : ""));
            return null;

        } catch (Exception $e) {
            error_log("S3 Error: " . $e->getMessage());
            return null;
        }
    }

    private function normalizeS3Key($key) {
        // Remove espaços em branco, quebras de linha e caracteres especiais
        $clean = trim($key);
        $clean = str_replace(["\n", "\r", " "], "", $clean);
        
        // Remove barras duplicadas
        $clean = preg_replace('#/+#', '/', $clean);
        
        // Remove barra inicial se existir
        $clean = ltrim($clean, '/');
        
        return $clean;
    }

    private function createPresignedUrl($key) {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, '+1 hour');
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log("Error creating presigned URL for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    public function putObject($key, $content, $contentType, $metadata = []) {
        try {
            return $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key'    => $key,
                'Body'   => $content,
                'ContentType' => $contentType,
                'Metadata' => $metadata
            ]);
        } catch (Exception $e) {
            error_log("S3 Error ao fazer upload: " . $e->getMessage());
            throw new Exception("Erro ao fazer upload para S3: " . $e->getMessage());
        }
    }

    public function deleteObject($key) {
        try {
            return $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);
        } catch (Exception $e) {
            error_log("S3 Error ao deletar objeto: " . $e->getMessage());
            throw new Exception("Erro ao deletar objeto do S3: " . $e->getMessage());
        }
    }
} 