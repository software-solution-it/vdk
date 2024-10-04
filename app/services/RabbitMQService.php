<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
require_once __DIR__ . '/../models/JobQueue.php';

class RabbitMQService {
    private $connection;
    private $channel;
    private $db;
    private $jobQueue;

    public function __construct($db) {
        $this->db = $db;
        $this->jobQueue = new JobQueue($db);  // Instancia o model JobQueue
        $this->connect();  // Conecta ao RabbitMQ
    }

    private function connect() {
        if (!$this->connection || !$this->channel) {
            // Inicializa a conexão com o RabbitMQ
            try {
                $this->connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
                $this->channel = $this->connection->channel();  // Agora podemos chamar o método channel() após a conexão ser criada.
            } catch (Exception $e) {
                error_log("Erro ao conectar ao RabbitMQ: " . $e->getMessage());
                throw new Exception("Não foi possível conectar ao RabbitMQ.");
            }
        }
    }

    public function clearQueue($queue_name) {
        // Remove a fila existente, se houver
        try {
            $this->channel->queue_delete($queue_name);
            error_log("Fila $queue_name foi removida.");
        } catch (Exception $e) {
            error_log("Erro ao remover a fila $queue_name: " . $e->getMessage());
        }
    }

    public function __destruct() {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }

    public function isQueueProcessing($queue_name) {
        $this->connect();  // Verifica se a conexão está aberta antes de executar

        try {
            // Garantir que a fila será criada se não existir
            $this->channel->queue_declare($queue_name, false, true, false, false);
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            if ($e->getCode() === 404) {
                return false;
            } else {
                throw $e;
            }
        }

        return true;  // Fila está sendo processada
    }

    public function publishMessage($queue_name, $message, $user_id) {
        // Declare a fila se ela não existir. O segundo parâmetro `false` garante que a fila será criada.
        $this->channel->queue_declare($queue_name, false, true, false, false);

        $msg = new AMQPMessage(json_encode($message), ['delivery_mode' => 2]);
        $this->channel->basic_publish($msg, '', $queue_name);

        $this->insertJobQueue($queue_name, $user_id);
    }

    public function consumeQueue($queue_name, $callback) {
        // Declare a fila antes de consumir, se ela não existir, será criada.
        $this->channel->queue_declare($queue_name, false, true, false, false);
        $this->channel->basic_consume($queue_name, '', false, true, false, false, function($msg) use ($callback) {
            $callback($msg);
            $this->markJobAsExecuted($msg->body);
        });

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    private function insertJobQueue($queue_name, $user_id) {
        $this->jobQueue->queue_name = $queue_name;
        $this->jobQueue->user_id = $user_id;
        return $this->jobQueue->create();
    }

    private function markJobAsExecuted($queue_name) {
        $pendingJobs = $this->jobQueue->getPendingJobs();
        foreach ($pendingJobs as $job) {
            if ($job['queue_name'] == $queue_name) {
                $this->jobQueue->markAsExecuted($job['id']);
                break;
            }
        }
    }
}
