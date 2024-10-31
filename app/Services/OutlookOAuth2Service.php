<?php
namespace App\Services;
require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../models/EmailAccount.php';

use League\OAuth2\Client\Provider\GenericProvider;
use App\Models\Email;
use PHPMailer\PHPMailer\Exception;
use App\Models\EmailAccount;
use PhpImap\Mailbox;

class OutlookOAuth2Service {
    private $emailModel;
    private $emailAccountModel;
    private $oauthProvider;

    public function __construct() {
        // Empty constructor for flexibility in later initialization
    }

    public function initialize($db) {
        // Initialize models with the database
        $this->emailModel = new Email($db);
        $this->emailAccountModel = new EmailAccount($db);
    }

    public function initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id) {
        // Encode user_id and provider_id information
        $extraParams = base64_encode(json_encode(['user_id' => $user_id, 'provider_id' => $provider_id]));

        // Create the redirect URI with embedded parameters
        $redirectUri = 'http://localhost:3000/callback?extra=' . urlencode($extraParams);

        // Create the OAuth provider with correct scope parameters
        $this->oauthProvider = new GenericProvider([
            'clientId'                => $emailAccount['client_id'],
            'clientSecret'            => $emailAccount['client_secret'],
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'defaultScopes'           => [
                'IMAP.AccessAsUser.All',
                'Mail.Read',
                'offline_access',
                'SMTP.Send',
                'User.Read',
                'User.Read.All'
            ],
        ]);

        // Generate the authorization URL with scopes as an array
        $authorizationUrl = $this->oauthProvider->getAuthorizationUrl([
            'scope' => [
                'IMAP.AccessAsUser.All',
                'Mail.Read',
                'offline_access',
                'SMTP.Send',
                'User.Read',
                'User.Read.All'
            ],
        ]);

        // Return the URL to the frontend
        return [
            'status' => true,
            'authorization_url' => $authorizationUrl
        ];
    }

    public function getAuthorizationUrl($user_id, $provider_id) {
        // Get the email account by user_id and provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Initialize the OAuthProvider if not configured
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        // Return the authorization URL
        return $this->oauthProvider->getAuthorizationUrl();
    }

    public function getAccessToken($user_id, $provider_id, $code) {
        // Get the email account by user_id and provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Initialize the OAuthProvider if not configured
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        try {
            // Get the access token using the authorization code
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Update the emailAccount with the new access token and refresh token
            if ($accessToken->getToken() && $accessToken->getRefreshToken()) {
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $accessToken->getToken(),
                    $accessToken->getRefreshToken(),
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );
            }

            return [
                'access_token' => $accessToken->getToken(),
                'refresh_token' => $accessToken->getRefreshToken()
            ];

        } catch (\Exception $e) {
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        }
    }

    public function refreshAccessToken($user_id, $provider_id) {
        // Get the email account by user_id and provider_id
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        // Initialize the OAuthProvider if not configured
        if (!$this->oauthProvider) {
            $this->initializeOAuthProviderFromEmailAccount($emailAccount, $user_id, $provider_id);
        }

        try {
            // Get a new access token using the refresh token
            $newAccessToken = $this->oauthProvider->getAccessToken('refresh_token', [
                'refresh_token' => $emailAccount['refresh_token']
            ]);

            // Update the emailAccount with the new access token and refresh token (if available)
            if ($newAccessToken->getToken()) {
                $this->emailAccountModel->update(
                    $emailAccount['id'],
                    $emailAccount['email'],
                    $emailAccount['provider_id'],
                    $emailAccount['password'],
                    $newAccessToken->getToken(),
                    $newAccessToken->getRefreshToken() ?: $emailAccount['refresh_token'],
                    $emailAccount['client_id'],
                    $emailAccount['client_secret']
                );
            }

            return [
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $emailAccount['refresh_token']
            ];

        } catch (\Exception $e) {
            throw new Exception('Failed to refresh access token: ' . $e->getMessage());
        }
    }

    public function authenticateImap($user_id, $provider_id) {
        $emailAccount = $this->emailAccountModel->getEmailAccountByUserIdAndProviderId($user_id, $provider_id);
        if (!$emailAccount) {
            throw new Exception("Email account not found for user ID: $user_id and provider ID: $provider_id");
        }

        $imapServer = '{outlook.office365.com:993/imap/ssl}INBOX';
        $accessToken = $emailAccount['oauth_token']; // The OAuth2 access token

        // Build the SASL XOAUTH2 login string
        $authString = base64_encode("user={$emailAccount['email']}\001auth=Bearer {$accessToken}\001\001");

        // Use imap_open with the correct parameters
        $imapStream = imap_open(
            $imapServer,
            $emailAccount['email'],
            $authString,
            0,
            1,
            ['AUTHENTICATOR' => 'XOAUTH2']
        );

        if (!$imapStream) {
            throw new Exception('Failed to authenticate with IMAP: ' . imap_last_error());
        }

        return $imapStream;
    }
}
