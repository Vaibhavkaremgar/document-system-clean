<?php
require_once __DIR__ . '/drive_oauth.php';

// Turn off output buffering issues
while (ob_get_level()) {
    ob_end_clean();
}

if (!isset($_GET['id'])) {
    exit('Missing file ID');
}

$fileId = $_GET['id'];

$service = getOAuthDriveService();

// Get file metadata
$file = $service->files->get($fileId, [
    'fields' => 'name,mimeType,size'
]);

// Download file content
$response = $service->files->get($fileId, ['alt' => 'media']);
$content = $response->getBody()->getContents();

// Safety check (debug)
if (empty($content)) {
    header('Content-Type: text/plain');
    echo 'ERROR: Empty file content';
    exit;
}

// Ensure filename has extension
$filename = $file->getName();
if (!pathinfo($filename, PATHINFO_EXTENSION)) {
    $mimeParts = explode('/', $file->getMimeType());
    $filename .= '.' . ($mimeParts[1] ?? 'bin');
}

// Headers
header('Content-Type: ' . $file->getMimeType());
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));

echo $content;
exit;
