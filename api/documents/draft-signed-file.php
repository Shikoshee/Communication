
<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();
$userId = (int)($user['id'] ?? 0);

$draftId = (int)($_GET['draft_id'] ?? 0);
$token = trim((string)($_GET['draft_token'] ?? ''));

if (
    $draftId <= 0 ||
    !preg_match('/^[a-f0-9]{64}$/i', $token)
) {
    http_response_code(400);
    exit('Invalid draft credentials.');
}

$draft = fetchRow(
    "SELECT signed_file_path, draft_token, status, expires_at
     FROM document_upload_drafts
     WHERE id = ? AND user_id = ?
     LIMIT 1",
    [$draftId, $userId]
);

if (
    !$draft ||
    !hash_equals((string)$draft['draft_token'], $token) ||
    strtolower((string)$draft['status']) !== 'draft' ||
    strtotime((string)$draft['expires_at']) <= time()
) {
    http_response_code(403);
    exit('Draft access denied or expired.');
}

$relativePath = str_replace(
    '\\',
    '/',
    (string)($draft['signed_file_path'] ?? '')
);

if (strpos($relativePath, 'uploads/documents/temp/') !== 0) {
    http_response_code(404);
    exit('Signed PDF not found.');
}

$tempRoot = realpath(__DIR__ . '/../../uploads/documents/temp');
$filePath = realpath(__DIR__ . '/../../' . $relativePath);

if (
    !$tempRoot ||
    !$filePath ||
    strpos($filePath, $tempRoot . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($filePath) ||
    filesize($filePath) <= 0
) {
    http_response_code(404);
    exit('Signed PDF not found.');
}

$handle = fopen($filePath, 'rb');
$signature = $handle ? fread($handle, 5) : '';

if ($handle) {
    fclose($handle);
}

if ($signature !== '%PDF-') {
    http_response_code(415);
    exit('The saved file is not a valid PDF.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

$download = isset($_GET['download']) && $_GET['download'] === '1';
header(
    'Content-Disposition: ' .
    ($download ? 'attachment' : 'inline') .
    '; filename="signed-document-' . $draftId . '.pdf"'
);

readfile($filePath);
exit;