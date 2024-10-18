<?php
namespace App\Models;
use PDO;
class MasterHost {
    private $conn;
    private $table_name = "master_host";

    public function __construct($db) {
        $this->conn = $db;
    }

public function getMasterHost() {
    $query = "SELECT 
                mh.id, 
                mh.email, 
                mh.password, 
                mh.provider_id, 
                mh.name, 
                mh.subject, 
                mh.created_at,
                p.smtp_host, 
                p.smtp_port, 
                p.encryption, 
                p.auth_type
              FROM " . $this->table_name . " mh
              JOIN mail.providers p ON mh.provider_id = p.id
              LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}
