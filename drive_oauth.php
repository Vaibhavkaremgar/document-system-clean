<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthDriveService() {
    $client = new Google\Client();
    $client->setApplicationName('Document System Drive Upload');
    $client->setScopes(Google\Service\Drive::DRIVE);
    $client->setAuthConfig(__DIR__ . '/oauth_credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = __DIR__ . '/token.json';

    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            $authUrl = $client->createAuthUrl();
            header('Location: ' . $authUrl);
            exit;
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }

    return new Google\Service\Drive($client);
}
