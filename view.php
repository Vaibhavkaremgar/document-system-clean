<?php
require_once __DIR__ . '/drive_oauth.php';

while (ob_get_level()) {
    ob_end_clean();
}

if (!isset($_GET['id'])) {
    exit('Missing file ID');
}

$fileId = $_GET['id'];

$service = getOAuthDriveService();

$file = $service->files->get($fileId, [
    'fields' => 'name,mimeType'
]);

$response = $service->files->get($fileId, ['alt' => 'media']);
$content = $response->getBody()->getContents();

if (empty($content)) {
    header('Content-Type: text/plain');
    echo 'ERROR: Empty file content';
    exit;
}

header('Content-Type: ' . $file->getMimeType());
header('Content-Disposition: inline; filename="' . $file->getName() . '"');

echo $content;
exit;
