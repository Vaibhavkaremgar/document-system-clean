<?php
// db.php
// Google Sheets + Google Drive configuration

require_once __DIR__ . '/vendor/autoload.php';

/* ==============================
   GOOGLE CLIENT SETUP
============================== */

$client = new Google_Client();
$client->setApplicationName('Local Document Management System');

// Path to Service Account JSON
//$client->setAuthConfig(__DIR__ . '/service-account.json');
$serviceAccount = json_decode(getenv('GOOGLE_SERVICE_ACCOUNT'), true);
$client->setAuthConfig($serviceAccount);

// Required scopes
$client->setScopes([
    Google_Service_Sheets::SPREADSHEETS,
    Google_Service_Drive::DRIVE
]);

/* ==============================
   GOOGLE SERVICES
============================== */

$sheetService = new Google_Service_Sheets($client);
$driveService = new Google_Service_Drive($client);

/* ==============================
   CONFIGURATION (CHANGE THESE)
============================== */

// Google Sheet ID (from Google Sheet URL)
$SPREADSHEET_ID = '17Lot0YOwP6sqdv9L35zRNPZzUJG8_jFnYhMjBR1AGJk';

// Google Drive ROOT folder ID (Family_Documents folder)
$DRIVE_FOLDER_ID = '1CMd7d4CX-W15si1SAuzymgmlu-V4M1so';
