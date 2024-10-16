<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Database;
use App\Services\EmailService;
use App\Services\RabbitMQService;
use PhpAmqpLib\Message\AMQPMessage;

$database = new Database();
$db = $database->getConnection();
$emailService = new EmailService($db);
$rabbitMQService = new RabbitMQService($db);

$queue_name = 'email_sending_queue';

$callback = function (AMQPMessage $msg) use ($emailService) {
    $messageBody = json_decode($msg->body, true);
    if (!$messageBody) {
        error_log("Erro ao decodificar a mensagem: " . $msg->body);
        $msg->nack(false, true);
        return;
    }

    if ($emailService->processEmailSending($messageBody)) {
        $msg->ack();
    } else {
        $msg->nack(false, true); 
    }
};

try {
    $rabbitMQService->consumeQueueContinuously($queue_name, $callback);
} catch (Exception $e) {
    error_log("Erro no consumidor de envio de e-mails: " . $e->getMessage());
}
