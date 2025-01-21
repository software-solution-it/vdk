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

            $cleanKey = $this->normalizeS3Key($key);
            error_log("Tentando gerar URL pré-assinada para chave: " . $cleanKey);

            try {
                $cmd = $this->s3Client->getCommand('GetObject', [
                    'Bucket' => $this->bucketName,
                    'Key'    => $cleanKey
                ]);
                
                // Configuração específica para a URL pré-assinada
                $presignedRequest = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region'  => 'sa-east-1',
                    'signature_version' => 'v4',
                    'credentials' => [
                        'key'    => $this->s3Client->getCredentials()->wait()->getAccessKeyId(),
                        'secret' => $this->s3Client->getCredentials()->wait()->getSecretKey(),
                    ]
                ]);
                
                // Gera a URL pré-assinada
                $url = $presignedRequest->createPresignedRequest(
                    $cmd,
                    '+7 days'
                )->getUri()->__toString();
                
                // Customiza a URL
                $customUrl = $this->customizeUrl($url);
                
                error_log("URL pré-assinada customizada gerada com sucesso: " . $customUrl);
                return $customUrl;

            } catch (Exception $e) {
                error_log("Erro ao gerar URL pré-assinada: " . $e->getMessage());
                return null;
            }

        } catch (Exception $e) {
            error_log("S3 Error: " . $e->getMessage());
            return null;
        }
    }

    private function customizeUrl($originalUrl) {
        // Parse a URL original
        $parsedUrl = parse_url($originalUrl);
        
        // Extrai os parâmetros da query
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        
        // Seu domínio customizado
        $customDomain = 'https://arquivos.seudominio.com.br';
        
        // Monta a nova URL mantendo apenas os parâmetros necessários
        $customUrl = $customDomain;
        $customUrl .= $parsedUrl['path'];
        
        // Adiciona os parâmetros de autenticação necessários
        $essentialParams = [
            'X-Amz-Algorithm' => $queryParams['X-Amz-Algorithm'] ?? '',
            'X-Amz-Credential' => $queryParams['X-Amz-Credential'] ?? '',
            'X-Amz-Date' => $queryParams['X-Amz-Date'] ?? '',
            'X-Amz-Expires' => $queryParams['X-Amz-Expires'] ?? '',
            'X-Amz-SignedHeaders' => $queryParams['X-Amz-SignedHeaders'] ?? '',
            'X-Amz-Signature' => $queryParams['X-Amz-Signature'] ?? ''
        ];
        
        // Adiciona os parâmetros à URL
        $customUrl .= '?' . http_build_query($essentialParams);
        
        return $customUrl;
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
            error_log("Gerando URL pré-assinada para: " . $key);
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $key
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, '+1 hour');
            $url = (string) $request->getUri();
            error_log("URL pré-assinada gerada com sucesso: " . $url);
            return $url;
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