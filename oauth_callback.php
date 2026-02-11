<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();

// credentials from env
$client->setAuthConfig(
    json_decode($_ENV['GOOGLE_CREDENTIALS'], true)
);

// 🔴 ADD THIS LINE HERE
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

$client->setScopes(Google\Service\Drive::DRIVE_FILE);
$client->setAccessType('offline');
$client->setPrompt('consent');

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
