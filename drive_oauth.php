<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthDriveService() {

    $client = new Google_Client();

    // ✅ Use ONLY the "web" OAuth config
    $credentials = json_decode($_ENV['GOOGLE_CREDENTIALS'], true);
    $client->setAuthConfig($credentials['web']);

    // ❌ DO NOT set redirect URI manually
    // It already exists in the OAuth config

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
            $authUrl = $client->createAuthUrl();
            header("Location: " . $authUrl);
            exit;
        }

        file_put_contents(
            $tokenPath,
            json_encode($client->getAccessToken())
        );
    }

    return new Google_Service_Drive($client);
}
