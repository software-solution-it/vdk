<?php

class Database {
    private $host = "betnext.cfkm8siwuqq1.sa-east-1.rds.amazonaws.com";
    private $db_name = "mail";
    private $username = "betadmin";
    private $password = "Cap0199**";
    private $charset = "utf8mb4";
    private $pdo;
    private $error;

    /**
     * Construtor que estabelece a conexão com o banco de dados ao instanciar a classe.
     */
    public function __construct() {
        $this->connect();
    }

    /**
     * Estabelece a conexão com o banco de dados.
     *
     * @throws Exception Se a conexão falhar.
     */
    public function connect() {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false, // Desativar conexões persistentes
            ];

            try {
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                error_log("Connection failed: " . $this->error);
                throw new Exception("Failed to connect to the database: " . $e->getMessage());
            }
        }
    }

    /**
     * Reestabelece a conexão com o banco de dados.
     *
     * @throws Exception Se a reconexão falhar.
     */
    public function reconnect() {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Retorna a instância PDO atual.
     * Verifica se a conexão está ativa antes de retornar.
     *
     * @return PDO
     * @throws Exception Se a conexão falhar.
     */
    public function getConnection() {
        if ($this->pdo === null) {
            $this->connect();
        }

        try {
            // Testa a conexão executando uma consulta simples
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // Se a consulta falhar, tenta reconectar
            error_log("Connection lost: " . $e->getMessage());
            $this->reconnect();
        }

        return $this->pdo;
    }

    /**
     * Fecha a conexão atual com o banco de dados.
     */
    public function disconnect() {
        $this->pdo = null;
    }
}
