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
    $file = $driveService->files->get($fileId, [
        'fields' => 'name,mimeType'
    ]);

    $response = $driveService->files->get($fileId, [
        'alt' => 'media'
    ]);

    header('Content-Type: ' . $file->getMimeType());
    header('Content-Disposition: attachment; filename="' . $file->getName() . '"');
    echo $response->getBody()->getContents();

} catch (Exception $e) {
    echo "ERROR DOWNLOADING FILE: " . $e->getMessage();
}
