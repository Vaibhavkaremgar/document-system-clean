<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'drive_oauth.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('File ID missing');
}

$fileId = $_GET['id'];
$driveService = getOAuthDriveService();

try {
    // Get metadata (IMPORTANT FLAGS)
    $file = $driveService->files->get($fileId, [
        'fields' => 'name,mimeType',
        'supportsAllDrives' => true
    ]);

    // Get content
    $response = $driveService->files->get($fileId, [
        'alt' => 'media',
        'supportsAllDrives' => true
    ]);

    header('Content-Type: ' . $file->getMimeType());
    header('Content-Disposition: inline; filename="' . $file->getName() . '"');
    echo $response->getBody()->getContents();
    exit;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
