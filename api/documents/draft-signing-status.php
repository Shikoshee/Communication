
<?php

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);
    exit;
}

$draftId = (int) ($_GET['draft_id'] ?? 0);
$draftToken = trim((string) ($_GET['draft_token'] ?? ''));

if (
    $draftId <= 0 ||
    !preg_match('/^[a-f0-9]{64}$/i', $draftToken)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid draft parameters.'
    ]);
    exit;
}

$draft = fetchRow(
    "SELECT id, user_id, draft_token, status, expires_at, signed_file_path
     FROM document_upload_drafts
     WHERE id = ? AND user_id = ?
     LIMIT 1",
    [$draftId, (int) $user['id']]
);

if (
    !$draft ||
    !hash_equals((string) $draft['draft_token'], $draftToken)
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Draft access denied.'
    ]);
    exit;
}

$expiry = strtotime((string) ($draft['expires_at'] ?? ''));

if ($expiry === false || $expiry <= time()) {
    echo json_encode([
        'success' => true,
        'signed' => false,
        'expired' => true
    ]);
    exit;
}

$signedPath = trim((string) ($draft['signed_file_path'] ?? ''));

echo json_encode([
    'success' => true,
    'signed' => (
        strtolower((string) $draft['status']) === 'draft' &&
        $signedPath !== ''
    ),
    'expired' => false
]);
