<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig(json_decode($_ENV['GOOGLE_CREDENTIALS'], true));

// ⚠️ CRITICAL: Set redirect URI
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

$client->setScopes(Google\Service\Drive::DRIVE_FILE);
$client->addScope(Google\Service\Sheets::SPREADSHEETS);
$client->setAccessType('offline');
$client->setPrompt('consent');

// Generate the authorization URL
$authUrl = $client->createAuthUrl();

// Redirect to Google
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;