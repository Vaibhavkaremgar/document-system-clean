<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Temporary debug - remove after fixing
echo '<pre>';
echo 'REDIRECT URI: ' . ($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?? 'NOT SET');
echo '<br>';
echo 'CLIENT ID: ' . ($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? 'NOT SET');
echo '<br>';
echo 'CLIENT SECRET: ' . (isset($_ENV['GOOGLE_CLIENT_SECRET']) ? 'SET' : 'NOT SET');
echo '</pre>';
die();

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI'));
$client->setScopes(Google\Service\Drive::DRIVE_FILE);
$client->setAccessType('offline');
$client->setPrompt('consent');

if (!isset($_GET['code'])) {
    exit('Authorization failed');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    exit('OAuth error: ' . $token['error_description']);
}

file_put_contents(__DIR__ . '/token.json', json_encode($token));

header('Location: index.php');
exit;
