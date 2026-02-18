<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthDriveService() {

    $client = new Google_Client();

    // ✅ OAuth credentials from ENV
    $client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI'));

    $client->setAccessType('offline');
    $client->setPrompt('consent select_account');
    $client->addScope(Google_Service_Drive::DRIVE);

    $tokenPath = __DIR__ . '/token.json';

    if (file_exists($tokenPath)) {
        $client->setAccessToken(
            json_decode(file_get_contents($tokenPath), true)
        );
    }

    if ($client->isAccessTokenExpired()) {

        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken(
                $client->getRefreshToken()
            );
        } else {
            header("Location: " . $client->createAuthUrl());
            exit;
        }

        file_put_contents(
            $tokenPath,
            json_encode($client->getAccessToken())
        );
    }

    return new Google_Service_Drive($client);
}
