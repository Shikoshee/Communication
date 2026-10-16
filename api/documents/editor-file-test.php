<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid document ID');
}

$document = fetchRow(
    "SELECT id, file_name, file_path, file_type
     FROM documents
     WHERE id = ?
       AND is_deleted = 0
     LIMIT 1",
    [$id]
);

if (!$document) {
    http_response_code(404);
    exit('Document not found');
}

$relativePath = ltrim(
    str_replace('\\', '/', $document['file_path']),
    '/'
);

$basePath = realpath(__DIR__ . '/../../');

$filePath = realpath(
    $basePath .
    DIRECTORY_SEPARATOR .
    str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        $relativePath
    )
);

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);

    echo "Physical file not found.<br>";
    echo "Database path: " .
        htmlspecialchars($document['file_path']);

    exit;
}

$extension = strtolower(
    pathinfo($document['file_name'], PATHINFO_EXTENSION)
);

$mimeTypes = [

    'doc' =>
        'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'docm' =>
        'application/vnd.ms-word.document.macroEnabled.12',

    'xls' =>
        'application/vnd.ms-excel',

    'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'pdf' =>
        'application/pdf',

];

$mimeType =
    $mimeTypes[$extension]
    ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);

header(
    'Content-Disposition: inline; filename="' .
    basename($document['file_name']) .
    '"'
);

header(
    'Content-Length: ' .
    filesize($filePath)
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

readfile($filePath);

exit;