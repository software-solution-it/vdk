<?php
namespace App\Controllers;
use Exception;
include __DIR__.'/vendor/autoload.php'; 


use Webklex\PHPIMAP\ClientManager;

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
        $access_token = $data['oauth2_token'];

        // Instanciar o ClientManager
        $cm = new ClientManager();

        // Configurar o cliente IMAP usando o token OAuth2
        $client = $cm->make([
            'host'          => 'outlook.office365.com',
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => false,
            'username'      => $email,
            'password'      => $access_token, // Aqui usamos o token OAuth2
            'protocol'      => 'imap',
            'authentication'=> 'oauth',       // Configurar para usar OAuth2
        ]);

        try {
            // Conectar ao servidor IMAP
            $client->connect();
            
            // Acessar a pasta INBOX e listar as mensagens
            $folder = $client->getFolder('INBOX');
            $all_messages = $folder->query()->all()->get();

            echo json_encode([
                'status' => 'success',
                'message_count' => count($all_messages)
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
