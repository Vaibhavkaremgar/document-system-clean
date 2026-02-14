<?php
require_once __DIR__ . '/vendor/autoload.php';

function getOAuthClient() {
    $client = new Google\Client();
    
    $client->setAuthConfig(json_decode($_ENV['GOOGLE_CREDENTIALS'], true));
    $client->addScope(Google\Service\Drive::DRIVE);
    $client->addScope(Google\Service\Sheets::SPREADSHEETS);
    
    $tokenPath = __DIR__ . '/token.json';
    
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
        
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
        }
    }
    
    return $client;
}

function getOAuthDriveService() {
    return new Google\Service\Drive(getOAuthClient());
}

function getOAuthSheetsService() {
    return new Google\Service\Sheets(getOAuthClient());
}