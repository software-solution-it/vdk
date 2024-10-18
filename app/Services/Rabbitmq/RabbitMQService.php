<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Models\JobQueue;
use Exception;

class RabbitMQService {
    private $connection;
    private $channel;
    private $db;
    private $jobQueue;

    public function __construct($db) {
        $this->db = $db;
        $this->jobQueue = new JobQueue($db); 
        $this->connect(); 
    }

    private function connect() {
        if (!$this->connection || !$this->channel) {
            try {
                $this->connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
                $this->channel = $this->connection->channel(); 
            } catch (Exception $e) {
                error_log("Erro ao conectar ao RabbitMQ: " . $e->getMessage());
                throw new Exception("Não foi possível conectar ao RabbitMQ.");
            }
        }
    }

    public function consumeQueueContinuously($queue_name, $callback) {
        $this->channel->queue_declare($queue_name, false, true, false, false);

        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume($queue_name, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function publishMessage($queue_name, $message, $user_id) {
        $this->channel->queue_declare($queue_name, false, true, false, false);

        $msg = new AMQPMessage(json_encode($message), ['delivery_mode' => 2]);
        $this->channel->basic_publish($msg, '', $queue_name);


        $this->insertJobQueue($queue_name, $user_id);
    }

    public function consumeQueue($queue_name, $callback) {
        $this->channel->queue_declare($queue_name, false, true, false, false);

        $this->channel->basic_qos(null, 1, null); 

        $this->channel->basic_consume($queue_name, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function insertJobQueue($queue_name, $user_id) {
        $this->jobQueue->queue_name = $queue_name;
        $this->jobQueue->user_id = $user_id;
        $this->jobQueue->is_executed = 0;
        $this->jobQueue->created_at = date('Y-m-d H:i:s');

        if (!$this->jobQueue->create()) {
            error_log("Erro ao inserir o job na tabela job_queue.");
        } else {
            error_log("Job inserido na tabela job_queue com sucesso.");
        }
    }

    public function markJobAsExecuted($queue_name) {
        $job = $this->jobQueue->getJobByQueueName($queue_name);
        if ($job) {
            $this->jobQueue->markAsExecuted($job['id']);
            error_log("Job com ID {$job['id']} marcado como executado para a fila {$queue_name}.");
        } else {
            error_log("Nenhum job encontrado para a fila {$queue_name}.");
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
}
