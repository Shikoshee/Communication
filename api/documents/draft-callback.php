
<?php

require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function draftCallbackResponse(int $error = 0): void
{
    http_response_code(200);
    echo json_encode(['error' => $error]);
    exit;
}

function draftCallbackLog(string $message): void
{
    error_log('[ONLYOFFICE DRAFT CALLBACK] ' . $message);
}

function draftCallbackDownload(string $url, string $destination): bool
{
    if (!function_exists('curl_init')) {
        draftCallbackLog('PHP cURL extension is not enabled.');
        return false;
    }

    $fp = fopen($destination, 'wb');

    if ($fp === false) {
        draftCallbackLog('Cannot create temporary save file.');
        return false;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FAILONERROR => true,
        CURLOPT_USERAGENT => 'CommunicationSystem-ONLYOFFICE/1.0',
    ]);

    $success = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);
    fclose($fp);

    clearstatcache(true, $destination);

    if (
        $success !== true ||
        $status !== 200 ||
        !is_file($destination) ||
        filesize($destination) < 1
    ) {
        draftCallbackLog(
            'Download failed. HTTP=' . $status .
            '; cURL=' . $error
        );

        if (is_file($destination)) {
            @unlink($destination);
        }

        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Validate draft ID and access token
|--------------------------------------------------------------------------
*/

$draftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$token = (string) ($_GET['token'] ?? '');

if (
    !$draftId ||
    $draftId <= 0 ||
    !preg_match('/^[a-f0-9]{64}$/i', $token)
) {
    draftCallbackLog('Invalid draft ID or access token.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Read ONLYOFFICE callback body
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data) || !isset($data['status'])) {
    draftCallbackLog('Invalid callback JSON.');
    draftCallbackResponse(1);
}

$status = (int) $data['status'];

/*
|--------------------------------------------------------------------------
| Callback status handling
|--------------------------------------------------------------------------
|
| 1 = document is being edited
| 2 = document is ready to be saved
| 3 = document save error
| 4 = document closed without changes
| 6 = force-save document is ready
| 7 = force-save error
|--------------------------------------------------------------------------
*/

if (!in_array($status, [1, 2, 3, 4, 6, 7], true)) {
    draftCallbackLog('Unsupported callback status: ' . $status);
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Statuses that do not require a download
|--------------------------------------------------------------------------
*/

if (in_array($status, [1, 4], true)) {
    draftCallbackResponse(0);
}

if (in_array($status, [3, 7], true)) {
    draftCallbackLog('ONLYOFFICE reported a document save error.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Status 2 and 6 require a document URL
|--------------------------------------------------------------------------
*/

$downloadUrl = (string) ($data['url'] ?? '');

if ($downloadUrl === '' || !filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
    draftCallbackLog('Save callback has no valid download URL.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Validate the download URL against the configured ONLYOFFICE host
|--------------------------------------------------------------------------
|
| This prevents the callback from being abused to fetch arbitrary URLs.
|--------------------------------------------------------------------------
*/

$docsUrl = defined('ONLYOFFICE_DOCS_URL')
    ? rtrim((string) ONLYOFFICE_DOCS_URL, '/')
    : '';

$allowedHost = strtolower((string) parse_url($docsUrl, PHP_URL_HOST));
$downloadHost = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
$downloadScheme = strtolower((string) parse_url($downloadUrl, PHP_URL_SCHEME));

if (
    $allowedHost === '' ||
    $downloadHost === '' ||
    $downloadHost !== $allowedHost ||
    $downloadScheme !== 'https'
) {
    draftCallbackLog('Rejected download URL host or scheme.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Load and validate draft
|--------------------------------------------------------------------------
*/

$draft = fetchRow(
    "SELECT
        id,
        draft_token,
        original_file_path,
        original_file_name,
        status,
        expires_at
     FROM document_upload_drafts
     WHERE id = ?
     LIMIT 1",
    [$draftId]
);

if (!$draft || !hash_equals((string) $draft['draft_token'], $token)) {
    draftCallbackLog('Draft not found or token mismatch.');
    draftCallbackResponse(1);
}

if (
    ($draft['status'] ?? '') !== 'draft' ||
    empty($draft['expires_at']) ||
    strtotime((string) $draft['expires_at']) <= time()
) {
    draftCallbackLog('Draft is no longer active.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Resolve permitted temporary directory
|--------------------------------------------------------------------------
*/

$projectRoot = realpath(dirname(__DIR__, 2));

if ($projectRoot === false) {
    draftCallbackLog('Project root could not be resolved.');
    draftCallbackResponse(1);
}

$tempDirectory = realpath(
    $projectRoot . DIRECTORY_SEPARATOR .
    'uploads' . DIRECTORY_SEPARATOR .
    'documents' . DIRECTORY_SEPARATOR .
    'temp'
);

if ($tempDirectory === false || !is_dir($tempDirectory)) {
    draftCallbackLog('Draft temporary directory is missing.');
    draftCallbackResponse(1);
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
    draftCallbackLog('Invalid draft file path.');
    draftCallbackResponse(1);
}

$originalPath = realpath(
    $projectRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $relativePath)
);

$normalizedTemp = rtrim(
    str_replace('\\', '/', $tempDirectory),
    '/'
);

$normalizedOriginal = $originalPath === false
    ? ''
    : str_replace('\\', '/', $originalPath);

if (
    $originalPath === false ||
    !is_file($originalPath) ||
    !is_writable(dirname($originalPath)) ||
    strncasecmp(
        $normalizedOriginal,
        $normalizedTemp . '/',
        strlen($normalizedTemp) + 1
    ) !== 0
) {
    draftCallbackLog('Draft file is missing or outside the temporary directory.');
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Download to a separate file, then atomically replace the draft
|--------------------------------------------------------------------------
|
| The original uploaded document is retained separately as the draft
| source file until the callback saves the current editor version.
|--------------------------------------------------------------------------
*/

$savePath = $originalPath . '.saving-' . bin2hex(random_bytes(8));

if (!draftCallbackDownload($downloadUrl, $savePath)) {
    draftCallbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Verify the downloaded file is a ZIP-based Office file when applicable
|--------------------------------------------------------------------------
*/

$extension = strtolower(
    pathinfo((string) $draft['original_file_name'], PATHINFO_EXTENSION)
);

$zipExtensions = ['docx', 'docm', 'dotx', 'xlsx', 'xlsm', 'pptx', 'pptm'];

if (in_array($extension, $zipExtensions, true)) {
    $handle = fopen($savePath, 'rb');
    $signature = $handle ? fread($handle, 4) : false;

    if ($handle) {
        fclose($handle);
    }

    if ($signature !== "PK\x03\x04") {
        @unlink($savePath);
        draftCallbackLog('Downloaded file is not a valid ZIP-based Office document.');
        draftCallbackResponse(1);
    }
}

/*
|--------------------------------------------------------------------------
| Replace the temporary draft file
|--------------------------------------------------------------------------
*/

if (!@rename($savePath, $originalPath)) {
    @unlink($savePath);
    draftCallbackLog('Could not replace the draft file.');
    draftCallbackResponse(1);
}

draftCallbackLog(
    'Saved draft ' . $draftId .
    ' using ONLYOFFICE callback status ' . $status . '.'
);

draftCallbackResponse(0);

