<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig(json_decode($_ENV['GOOGLE_CREDENTIALS'], true));
$client->setRedirectUri('https://document-system-production-1a7e.up.railway.app/setup.php');
$client->addScope(Google\Service\Drive::DRIVE);
$client->addScope(Google\Service\Sheets::SPREADSHEETS);
$client->setAccessType('offline');
$client->setPrompt('consent');

if (!isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    echo '<h2>Admin Setup - Authorize Application</h2>';
    echo '<a href="' . $authUrl . '" style="padding:10px 20px; background:#4285f4; color:white; text-decoration:none; border-radius:4px;">Click Here to Authorize</a>';
} else {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        die('Error: ' . $token['error']);
    }
    file_put_contents(__DIR__ . '/token.json', json_encode($token));
    echo '<h2>Success!</h2>';
    echo '<p>token.json has been created. Your app is now authorized.</p>';
    echo '<p><strong>Next steps:</strong></p>';
    echo '<ul>';
    echo '<li>Delete setup.php from your project</li>';
    echo '<li>Users can now use your app without logging in</li>';
    echo '</ul>';
}