
<?php

require_once __DIR__ . '/../../includes/config.php';

/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
*/

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

/*
|--------------------------------------------------------------------------
| ERROR RESPONSE
|--------------------------------------------------------------------------
*/

function draftFileError(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

/*
|--------------------------------------------------------------------------
| REQUEST PARAMETERS
|--------------------------------------------------------------------------
*/

$draftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$token = (string) ($_GET['token'] ?? '');

if (!$draftId || $draftId <= 0) {
    draftFileError(400, 'Invalid draft ID.');
}

if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    draftFileError(403, 'Invalid draft access token.');
}

/*
|--------------------------------------------------------------------------
| FIND DRAFT AND VERIFY TOKEN
|--------------------------------------------------------------------------
|
| The random draft token acts as a temporary access credential.
| ONLYOFFICE can retrieve the file without a browser login session.
|--------------------------------------------------------------------------
*/

$draft = fetchRow(
    "SELECT
        id,
        user_id,
        draft_token,
        original_file_name,
        original_file_path,
        status,
        expires_at
     FROM document_upload_drafts
     WHERE id = ?
     LIMIT 1",
    [$draftId]
);

if (!$draft) {
    draftFileError(404, 'Draft not found.');
}

if (!hash_equals(
    (string) $draft['draft_token'],
    $token
)) {
    draftFileError(403, 'Draft access denied.');
}

/*
|--------------------------------------------------------------------------
| CHECK DRAFT STATUS AND EXPIRATION
|--------------------------------------------------------------------------
*/

if (($draft['status'] ?? '') !== 'draft') {
    draftFileError(410, 'This draft is no longer available.');
}

if (strtotime((string) $draft['expires_at']) <= time()) {
    draftFileError(410, 'This draft has expired. Please upload the document again.');
}

/*
|--------------------------------------------------------------------------
| RESOLVE FILE PATH SAFELY
|--------------------------------------------------------------------------
*/

$projectRoot = realpath(dirname(__DIR__, 2));

if ($projectRoot === false) {
    draftFileError(500, 'Application directory could not be resolved.');
}

$relativePath = str_replace(
    '\\',
    '/',
    (string) $draft['original_file_path']
);

if (
    $relativePath === '' ||
    str_contains($relativePath, '..') ||
    str_starts_with($relativePath, '/')
) {
    draftFileError(403, 'Invalid stored file path.');
}

$absolutePath = realpath(
    $projectRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $relativePath)
);

$expectedTempDirectory = realpath(
    $projectRoot . DIRECTORY_SEPARATOR .
    'uploads' . DIRECTORY_SEPARATOR .
    'documents' . DIRECTORY_SEPARATOR .
    'temp'
);

if (
    $absolutePath === false ||
    $expectedTempDirectory === false ||
    !is_file($absolutePath) ||
    !is_readable($absolutePath)
) {
    draftFileError(404, 'Draft file is missing or unreadable.');
}

/*
|--------------------------------------------------------------------------
| ENSURE THE FILE IS INSIDE THE TEMPORARY DIRECTORY
|--------------------------------------------------------------------------
*/

$normalizedRoot = rtrim(
    str_replace('\\', '/', $expectedTempDirectory),
    '/'
);

$normalizedFile = str_replace('\\', '/', $absolutePath);

if (
    strncasecmp(
        $normalizedFile,
        $normalizedRoot . '/',
        strlen($normalizedRoot) + 1
    ) !== 0
) {
    draftFileError(403, 'Draft file path is outside the permitted directory.');
}

/*
|--------------------------------------------------------------------------
| DETERMINE FILE TYPE
|--------------------------------------------------------------------------
*/

$extension = strtolower(
    pathinfo((string) $draft['original_file_name'], PATHINFO_EXTENSION)
);

$mimeTypes = [
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
    'dot'  => 'application/msword',
    'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'rtf'  => 'application/rtf',
    'txt'  => 'text/plain'
];

if (!isset($mimeTypes[$extension])) {
    draftFileError(415, 'Unsupported draft file type.');
}

/*
|--------------------------------------------------------------------------
| STREAM THE FILE TO ONLYOFFICE
|--------------------------------------------------------------------------
*/

$size = filesize($absolutePath);

if ($size === false) {
    draftFileError(500, 'Could not determine the draft file size.');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . $size);
header(
    'Content-Disposition: inline; filename="' .
    rawurlencode(basename((string) $draft['original_file_name'])) .
    '"'
);

readfile($absolutePath);
exit;

