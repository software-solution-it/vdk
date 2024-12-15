<?php
namespace App\Config;
use PDO;
use PDOException;
use Exception;

class Database {
    private $host = "149.18.103.156"; 
    private $db_name = "mail";   
    private $username = "nvm_prd";  
    private $password = "Cap0199**";   
    private $charset = "utf8";
    private $pdo;    
    private $error;      

    public function __construct() {
        $this->connect();          
    }

    public function connect() {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};port=3306;dbname={$this->db_name};charset={$this->charset}"; 
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false, 
            ];

            try {
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options); 
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                error_log("Connection failed: " . $this->error); // Log de erro
                throw new Exception("Failed to connect to the database: " . $e->getMessage());
            }
        }
    }

    public function reconnect() {
        $this->disconnect();      
        $this->connect();
    }

    public function getConnection() {
        if ($this->pdo === null) {
            $this->connect();
        }

        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            error_log("Connection lost: " . $e->getMessage()); 
            $this->reconnect();
        }

        return $this->pdo;  
    }

    public function disconnect() {
        $this->pdo = null;   
    }
}
