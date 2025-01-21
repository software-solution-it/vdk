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
            error_log("Tentando gerar URL pré-assinada para chave: " . $cleanKey);

            // Verifica se o objeto existe
            try {
                $exists = $this->s3Client->doesObjectExist($this->bucketName, $cleanKey);
                error_log("Objeto existe no caminho original? " . ($exists ? "Sim" : "Não"));
                
                if ($exists) {
                    return $this->createPresignedUrl($cleanKey);
                }

                // Se não existe, tenta outras variações do caminho
                $variations = [
                    $cleanKey,
                    "inline-images/" . basename(dirname($cleanKey)) . "/" . basename($cleanKey),
                    str_replace("attachments/", "", $cleanKey),
                    str_replace("inline-images/", "", $cleanKey)
                ];

                foreach ($variations as $path) {
                    error_log("Tentando caminho alternativo: " . $path);
                    if ($this->s3Client->doesObjectExist($this->bucketName, $path)) {
                        error_log("Objeto encontrado em: " . $path);
                        return $this->createPresignedUrl($path);
                    }
                }

                // Se ainda não encontrou, tenta listar objetos com o mesmo hash
                $prefix = basename(dirname($cleanKey));
                $result = $this->s3Client->listObjects([
                    'Bucket' => $this->bucketName,
                    'Prefix' => $prefix
                ]);

                error_log("Objetos encontrados com prefix {$prefix}:");
                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        error_log("- " . $object['Key']);
                    }
                }

                error_log("Nenhum objeto encontrado para a chave: " . $cleanKey);
                return null;

            } catch (Exception $e) {
                error_log("Erro ao verificar existência do objeto: " . $e->getMessage());
                throw $e;
            }

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
        
        error_log("Normalizando chave: " . $key . " -> " . $clean);
        
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