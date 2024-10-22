<?php

require __DIR__ . '/vendor/autoload.php';
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ErrorLogController;
use App\Controllers\EmailController;
use App\Controllers\EmailAccountController;
use App\Controllers\ProviderController;
use App\Controllers\EmailSyncController;
use App\Controllers\ConnectionController;
use App\Controllers\WebhookController;
use App\Controllers\ImapTestController;
use App\Helpers\AuthMiddleware;

//AuthMiddleware::verifyBearerToken();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");

$request_uri = explode('?', $_SERVER['REQUEST_URI'], 2);

switch ($request_uri[0]) {
    case '/auth/login':
        $auth = new AuthController();
        $auth->login();
        break;

    case '/auth/verify-code':
        $auth = new AuthController();
        $auth->verifyLoginCode();
        break;

    case '/auth/forgot-password':
        $auth = new AuthController();
        $auth->forgotPassword();
        break;

    case '/auth/reset-password':
        $auth = new AuthController();
        $auth->resetPassword();
        break;

    case '/auth/register':
        $auth = new AuthController();
        $auth->preRegister();
        break;

    case '/auth/resend-code':
        $auth = new AuthController();
        $auth->resendCode();
        break;

    case '/user/list':
        $user = new UserController();
        $user->listUsers();
        break;

    case '/user/get':
        $user = new UserController();
        $user->getUserById();
        break;

    case '/user/update':
        $user = new UserController();
        $user->updateUser();
        break;

    case '/user/delete':
        $user = new UserController();
        $user->deleteUser();
        break;

    case '/user/check-access':
        $user = new UserController();
        $user->checkUserAccess();
        break;

    case '/email/send':
        $emailController = new EmailController();
        $emailController->sendEmail();
        break;


    case '/email/check':
        $domain = $_GET['domain'] ?? null; // Obtém o domínio da requisição
        $emailController = new EmailController();
        $emailController->checkDomain($domain); // Passa o domínio para o método checkDomain

        break;

    case '/email/sendMultiple':
        $emailController = new EmailController();
        $emailController->sendMultipleEmails();
        break;

        case '/imap/test':
            $imapTest = new ImapTestController(); // Cria uma instância do controlador de teste IMAP
            $imapTest->testImap(); // Chama o método para testar IMAP
            break;
    

    case '/email/list':
        $emailController = new EmailController();
        $user_id = $_GET['user_id'] ?? null;
        $folder = $_GET['folder'] ?? 'INBOX';
        $search = $_GET['search'] ?? '';
        $emailController->listEmails($user_id, $folder, $search);
        break;

    case '/email/view':
        $emailController = new EmailController();
        $email_id = $_GET['email_id'] ?? null;
        $emailController->viewEmail($email_id);
        break;

    case '/email/mark-spam':
        $emailController = new EmailController();
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['user_id']) && isset($data['provider_id']) && isset($data['email_id'])) {
            $emailController->markEmailAsSpam($data['user_id'], $data['provider_id'], $data['email_id']);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Os seguintes parâmetros estão faltando: user_id, provider_id, email_id']);
        }
        break;

    case '/email/delete-spam':
        $emailController = new EmailController();
        $emailController->deleteSpamEmail($_POST['user_id'], $_POST['email_id']);
        break;

    case '/email/unmark-spam':
        $emailController = new EmailController();
        $emailController->unmarkSpam($_POST['user_id'], $_POST['email_id'], $_POST['destinationFolder'] ?? 'INBOX');
        break;

    case '/email/create':
        $controller = new EmailAccountController();
        $controller->createEmailAccount();
        break;

    case '/email/account':
        $userId = $_GET['user_id'] ?? null; // Obtém o user_id da requisição
        if ($userId) {
            $controller = new EmailAccountController();
            $controller->getEmailAccountByUserId($userId); // Passa o userId para o método
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['message' => 'User ID is required']);
        }
        break;
    case '/email/update':
        $controller = new EmailAccountController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->updateEmailAccount($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/email/delete':
        $controller = new EmailAccountController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->deleteEmailAccount($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;


    case '/provider/create':
        $controller = new ProviderController();
        $controller->createProvider();
        break;

    case '/provider/update':
        $controller = new ProviderController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->updateProvider($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/email/sync/consume':
        $emailSync = new EmailSyncController();
        $emailSync->startConsumer();
        break;

    case '/test/smtp':
        $connection = new ConnectionController();
        $connection->testSMTP();
        break;

    case '/test/imap':
        $connection = new ConnectionController();
        $connection->testIMAP();
        break;

    case '/provider/delete':
        $controller = new ProviderController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->deleteProvider($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/error/log':
        $errorLogController = new ErrorLogController();
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['error_message'], $data['file'], $data['line'], $data['user_id'])) {
            $errorLogController->logError($data['error_message'], $data['file'], $data['line'], $data['user_id'], $data['additional_info'] ?? null);
        } else {
            echo json_encode(['status' => false, 'message' => 'Missing fields for error logging']);
        }
        break;

    case '/error/logs':
        $errorLogController = new ErrorLogController();
        $userId = $_GET['user_id'] ?? null;
        if ($userId) {
            $errorLogController->getLogsByUserId($userId);
        } else {
            $errorLogController->getLogs();
        }
        break;

    case '/provider/list':
        $controller = new ProviderController();
        $controller->getAllProviders();
        break;

    case '/webhook/list':
        $webhook = new WebhookController();
        $webhook->getList();
        break;

    case '/webhook/register':
        $webhook = new WebhookController();
        $webhook->registerWebhook();
        break;

}
