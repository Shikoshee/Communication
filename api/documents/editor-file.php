<?php

require_once "../../includes/config.php";

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}


/*
|--------------------------------------------------------------------------
| Get parameters
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

$token = trim(
    (string)($_GET['token'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Validate document ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    http_response_code(400);

    exit('Invalid document ID');
}


/*
|--------------------------------------------------------------------------
| Validate token
|--------------------------------------------------------------------------
*/

if ($token === '') {

    http_response_code(403);

    exit('Missing document access token');
}


/*
|--------------------------------------------------------------------------
| Generate expected token
|--------------------------------------------------------------------------
*/

$expectedToken = hash_hmac(
    'sha256',
    (string)$id,
    ONLYOFFICE_JWT_SECRET
);


/*
|--------------------------------------------------------------------------
| Secure token comparison
|--------------------------------------------------------------------------
*/

if (!hash_equals($expectedToken, $token)) {
    error_log(
        '[ONLYOFFICE] Token mismatch for document ID ' . $id .
        '; received length=' . strlen($token) .
        '; expected length=' . strlen($expectedToken) .
        '; secret defined=' .
        (defined('ONLYOFFICE_JWT_SECRET') ? 'yes' : 'no')
    );

    http_response_code(403);
    exit('Invalid document access token');
}

/*
|--------------------------------------------------------------------------
| Get document
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "SELECT
        *
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


/*
|--------------------------------------------------------------------------
| Get stored file path
|--------------------------------------------------------------------------
*/

$filePath = trim(
    (string)($document['file_path'] ?? '')
);


if ($filePath === '') {

    http_response_code(404);

    exit('Document file path is empty');
}


/*
|--------------------------------------------------------------------------
| Application root
|--------------------------------------------------------------------------
*/

$appRoot = realpath(
    __DIR__ . '/../../'
);


if ($appRoot === false) {

    http_response_code(500);

    exit('Application root could not be resolved');
}


/*
|--------------------------------------------------------------------------
| Resolve document path
|--------------------------------------------------------------------------
|
| Database paths normally look like:
|
| uploads/documents/example.docx
|
*/

$relativePath = str_replace(
    ['/', '\\'],
    DIRECTORY_SEPARATOR,
    $filePath
);

$relativePath = ltrim(
    $relativePath,
    DIRECTORY_SEPARATOR
);


$fullPath = realpath(
    $appRoot .
    DIRECTORY_SEPARATOR .
    $relativePath
);


/*
|--------------------------------------------------------------------------
| Verify physical file
|--------------------------------------------------------------------------
*/

if ($fullPath === false || !is_file($fullPath)) {

    http_response_code(404);

    exit('Document file not found');
}


/*
|--------------------------------------------------------------------------
| Security: ensure file remains inside application
|--------------------------------------------------------------------------
*/

$appRootNormalized = rtrim(
    realpath($appRoot),
    DIRECTORY_SEPARATOR
);

$filePathNormalized = realpath($fullPath);


if (
    $filePathNormalized !== $appRootNormalized &&
    strpos(
        $filePathNormalized,
        $appRootNormalized . DIRECTORY_SEPARATOR
    ) !== 0
) {

    http_response_code(403);

    exit('Invalid document path');
}


/*
|--------------------------------------------------------------------------
| Determine MIME type
|--------------------------------------------------------------------------
*/

$extension = strtolower(
    pathinfo(
        $fullPath,
        PATHINFO_EXTENSION
    )
);


$mimeTypes = [

    'doc' =>
        'application/msword',

    'docx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'docm' =>
        'application/vnd.ms-word.document.macroEnabled.12',

    'dot' =>
        'application/msword',

    'dotx' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template',

    'xls' =>
        'application/vnd.ms-excel',

    'xlsx' =>
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

    'xlsm' =>
        'application/vnd.ms-excel.sheet.macroEnabled.12',

    'ppt' =>
        'application/vnd.ms-powerpoint',

    'pptx' =>
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    'pptm' =>
        'application/vnd.ms-powerpoint.presentation.macroEnabled.12',

    'pdf' =>
        'application/pdf',

    'odt' =>
        'application/vnd.oasis.opendocument.text',

    'ods' =>
        'application/vnd.oasis.opendocument.spreadsheet',

    'odp' =>
        'application/vnd.oasis.opendocument.presentation',

    'rtf' =>
        'application/rtf',

    'txt' =>
        'text/plain',

    'csv' =>
        'text/csv'

];


$mimeType =
    $mimeTypes[$extension]
    ?? 'application/octet-stream';


/*
|--------------------------------------------------------------------------
| File information
|--------------------------------------------------------------------------
*/

$fileSize = filesize($fullPath);

if ($fileSize === false) {

    http_response_code(500);

    exit('Unable to determine document size');
}


/*
|--------------------------------------------------------------------------
| Send document
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: ' . $mimeType
);

header(
    'Content-Length: ' . $fileSize
);

header(
    'Content-Disposition: inline; filename="' .
    basename($fullPath) .
    '"'
);

header(
    'Content-Transfer-Encoding: binary'
);

header(
    'Accept-Ranges: bytes'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| HEAD request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}


/*
|--------------------------------------------------------------------------
| Output file
|--------------------------------------------------------------------------
*/

readfile($fullPath);

exit;