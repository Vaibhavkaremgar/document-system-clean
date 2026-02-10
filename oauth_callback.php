<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/oauth_credentials.json');
$client->setRedirectUri('http://localhost/document-system/oauth_callback.php');
$client->setScopes(Google\Service\Drive::DRIVE_FILE);

if (!isset($_GET['code'])) {
    exit('Authorization failed');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    exit('OAuth error');
}

file_put_contents(__DIR__ . '/token.json', json_encode($token));

header('Location: index.php');
exit;
