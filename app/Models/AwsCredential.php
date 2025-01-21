<?php

namespace App\Models;

class AwsCredential
{
    private $db;
    private $table = 'aws_credentials';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getCredentials()
    {
        try {
            $query = "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Erro ao buscar credenciais AWS: " . $e->getMessage());
            return null;
        }
    }

    public function updateCredentials($accessKeyId, $secretAccessKey, $region = 'sa-east-1', $bucket = 'vdkmail', $endpoint = null)
    {
        try {
            $query = "INSERT INTO {$this->table} 
                    (access_key_id, secret_access_key, region, bucket, endpoint) 
                    VALUES (:access_key_id, :secret_access_key, :region, :bucket, :endpoint)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':access_key_id' => $accessKeyId,
                ':secret_access_key' => $secretAccessKey,
                ':region' => $region,
                ':bucket' => $bucket,
                ':endpoint' => $endpoint
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar credenciais AWS: " . $e->getMessage());
            return false;
        }
    }
} 