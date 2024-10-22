<?php

namespace App\Controllers;

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
            // Gerar o token XOAUTH2 para autenticação
            $auth_string = base64_encode("user=$email\1auth=Bearer " . $oauth2_token . "\1\1");

            // Conectar ao servidor IMAP
            $imap_stream = imap_open(
                '{outlook.office365.com:993/imap/ssl}INBOX',
                $email,
                $auth_string,
                OP_HALFOPEN
            );

            if (!$imap_stream) {
                throw new Exception("Erro de autenticação: " . imap_last_error());
            }

            // Se a conexão for bem-sucedida, retornar as pastas
            $mailboxes = imap_list($imap_stream, '{outlook.office365.com:993/imap/ssl}', '*');
            echo json_encode(['status' => 'success', 'mailboxes' => $mailboxes]);

            // Fechar a conexão
            imap_close($imap_stream);
        } catch (Exception $e) {
            // Loga o erro e retorna a mensagem de erro
            error_log("Erro ao autenticar via IMAP: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
