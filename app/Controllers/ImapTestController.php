<?php

namespace App\Controllers;

use Ddeboer\Imap\Server;
use Exception;

class ImapTestController
{
    public function testImap()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['oauth2_token'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => false, 'message' => 'Email and OAuth2 token are required']);
            return;
        }

        $email = $data['email'];
        $oauth2_token = $data['oauth2_token'];

        try {
            // Conectar ao servidor IMAP
            $server = new Server('outlook.office365.com', 993);
            $connection = $server->authenticate($email, $oauth2_token); // Usa OAuth2

            // Se a conexão for bem-sucedida, retornar as pastas
            $mailboxes = $connection->getMailboxes();
            echo json_encode(['status' => 'success', 'mailboxes' => $mailboxes]);
        } catch (Exception $e) {
            // Loga o erro e retorna a mensagem de erro
            error_log("Erro ao autenticar via IMAP: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
