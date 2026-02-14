<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthDriveService() {

    $client = new Google_Client();

    // ✅ Credentials from Railway env
    $client->setAuthConfig(
        json_decode($_ENV['GOOGLE_CREDENTIALS'], true)
    );

    // ✅ ADD THIS LINE (CRITICAL)
    $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

    $client->setAccessType('offline'); // required for refresh token
    $client->setPrompt('select_account consent');
    $client->addScope(Google_Service_Drive::DRIVE);

    $tokenPath = __DIR__ . '/token.json';

    // Load existing token
    if (file_exists($tokenPath)) {
        $client->setAccessToken(
            json_decode(file_get_contents($tokenPath), true)
        );
    }

    // If token expired → refresh or re-auth
    if ($client->isAccessTokenExpired()) {

        if ($client->getRefreshToken()) {
            // Refresh token
            $client->fetchAccessTokenWithRefreshToken(
                $client->getRefreshToken()
            );
        } 
        else {
            // No refresh token → force login
            $authUrl = $client->createAuthUrl();
            header("Location: " . $authUrl);
            exit;
        }

        // Save updated token
        file_put_contents(
            $tokenPath,
            json_encode($client->getAccessToken())
        );
    }

    return new Google_Service_Drive($client);
}
