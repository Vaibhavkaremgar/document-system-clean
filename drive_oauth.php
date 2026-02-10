<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthDriveService(){

    $client = new Google_Client();
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline'); // REQUIRED for refresh token
    $client->setPrompt('select_account consent');
    $client->addScope(Google_Service_Drive::DRIVE);

    $tokenPath = 'token.json';

    // Load existing token
    if (file_exists($tokenPath)) {
        $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
    }

    // If token expired → refresh or re-auth
    if ($client->isAccessTokenExpired()) {

        if ($client->getRefreshToken()) {
            // Refresh token
            $client->fetchAccessTokenWithRefreshToken(
                $client->getRefreshToken()
            );
        } else {
            // No refresh token → force login
            $authUrl = $client->createAuthUrl();
            header("Location: ".$authUrl);
            exit;
        }

        // 🔥 VERY IMPORTANT: save new token
        file_put_contents(
            $tokenPath,
            json_encode($client->getAccessToken())
        );
    }

    return new Google_Service_Drive($client);
}

