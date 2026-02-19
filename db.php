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
/*$serviceAccount = json_decode(getenv('GOOGLE_SERVICE_ACCOUNT'), true);
if (!$serviceAccount) {
    die("Service account JSON is invalid");
//}
$client->setAuthConfig($serviceAccount);*/
$serviceAccountJson = getenv('GOOGLE_SERVICE_ACCOUNT');
$serviceAccount = json_decode($serviceAccountJson, true);
if (!$serviceAccount) {
    die("Error: " . json_last_error_msg());
}
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
$SPREADSHEET_ID = '1Wm_FE5dr1Z9Ik0p9qDt4-XHougPkUP00CGoMdd-9bDw';

// Google Drive ROOT folder ID (Family_Documents folder)
$DRIVE_FOLDER_ID = '1odnEcdCIz_g-Lnvma2IYogFqIl_LBrlA';
