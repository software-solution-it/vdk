<?php
require __DIR__ . '/../vendor/autoload.php';

$request_uri = explode('?', $_SERVER['REQUEST_URI'], 2);


switch ($request_uri[0]) {
    case '/auth/login':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->login();
        break;

    case '/auth/verify-code':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->verifyLoginCode();
        break;

    case '/auth/forgot-password':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->forgotPassword();
        break;

    case '/auth/reset-password':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->resetPassword();
        break;

    case '/auth/register':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->preRegister();
        break;

    case '/auth/resend-code':
        require 'controllers/AuthController.php';
        $auth = new AuthController();
        $auth->resendCode();
        break;

    case '/user/list':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->listUsers();
        break;

    case '/user/get':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->getUserById();
        break;

    case '/user/create':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->createUser();
        break;

    case '/user/update':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->updateUser();
        break;

    case '/user/delete':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->deleteUser();
        break;

    case '/user/check-access':
        require 'controllers/UserController.php';
        $user = new UserController();
        $user->checkUserAccess();
        break;

    case '/email/send':
        require 'controllers/EmailController.php';
        $emailController = new EmailController();
        $emailController->sendEmail();
        break;

    case '/email/create':
        require 'controllers/EmailAccountController.php';
        $controller = new EmailAccountController();
        $controller->createEmailAccount();
        break;

    case '/email/update':
        require 'controllers/EmailAccountController.php';
        $controller = new EmailAccountController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->updateEmailAccount($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/email/delete':
        require 'controllers/EmailAccountController.php';
        $controller = new EmailAccountController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->deleteEmailAccount($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/email/account':
        require 'controllers/EmailAccountController.php';
        $controller = new EmailAccountController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->getEmailAccountById($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/provider/create':
        require 'controllers/ProviderController.php';
        $controller = new ProviderController();
        $controller->createProvider();
        break;

    case '/provider/update':
        require 'controllers/ProviderController.php';
        $controller = new ProviderController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->updateProvider($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/email/sync':
        require 'controllers/EmailSyncController.php';
        $emailSync = new EmailSyncController();
        $emailSync->syncEmails();
        break;

    case '/email/sync/consume':
        require 'controllers/EmailSyncController.php';
        $emailSync = new EmailSyncController();
        $emailSync->startConsumer();
        break;

    case '/test/smtp':
        require 'controllers/ConnectionController.php';
        $connection = new ConnectionController();
        $connection->testSMTP();
        break;


    case '/test/imap':
        require 'controllers/ConnectionController.php';
        $connection = new ConnectionController();
        $connection->testIMAP();
        break;

    case '/provider/delete':
        require 'controllers/ProviderController.php';
        $controller = new ProviderController();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $controller->deleteProvider($id);
        } else {
            echo json_encode(['status' => false, 'message' => 'ID is required']);
        }
        break;

    case '/provider/list':
        require 'controllers/ProviderController.php';
        $controller = new ProviderController();
        $controller->getAllProviders();
        break;

    case '/webhook/register':
        require 'controllers/WebhookController.php';
        $webhook = new WebhookController();
        $webhook->registerWebhook();
        break;

    case '/webhook/trigger':
        require 'controllers/WebhookController.php';
        $webhook = new WebhookController();
        $webhook->triggerWebhook();
        break;

    case '/campaign/create':
        require 'controllers/CampaignController.php';
        $controller = new CampaignController();
        $controller->createCampaign();
        break;

    case '/campaign/read-all':
        require 'controllers/CampaignController.php';
        $controller = new CampaignController();
        $controller->readAllCampaigns();
        break;

    case '/campaign/read-by-id':
        require 'controllers/CampaignController.php';
        $controller = new CampaignController();
        $controller->readCampaignById();
        break;

    case '/campaign/update':
        require 'controllers/CampaignController.php';
        $controller = new CampaignController();
        $controller->updateCampaign();
        break;

    case '/campaign/delete':
        require 'controllers/CampaignController.php';
        $controller = new CampaignController();
        $controller->deleteCampaign();
        break;


    case '/scheduled-email/create':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->createScheduledEmail();
        break;

    case '/scheduled-email/read-all':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->readAllScheduledEmail();
        break;

    case '/scheduled-email/read-by-id':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->readScheduledEmailById();
        break;

    case '/scheduled-email/update':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->updateScheduledEmail();
        break;

    case '/scheduled-email/delete':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->deleteScheduledEmail();
        break;

    case '/scheduled-email/send-all':
        require 'controllers/ScheduledEmailController.php';
        $controller = new ScheduledEmailController();
        $controller->sendAllEmails();
        break;


    default:
        header('HTTP/1.0 404 Not Found');
        echo json_encode(['message' => '404 Not Found']);
        break;
}
