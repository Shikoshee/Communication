
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

$documentId = (int) ($_GET['document_id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid document ID.'
    ]);
    exit;
}

$document = fetchRow(
    "SELECT id, uploaded_by, file_path, reviewed_file
     FROM documents
     WHERE id = ?
       AND COALESCE(is_deleted, 0) = 0
     LIMIT 1",
    [$documentId]
);

if (!$document) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Document not found.'
    ]);
    exit;
}

/*
 * Allow the uploader or an authorized approver to check signing status.
 */
$userId = (int) $user['id'];
$isUploader = (int) ($document['uploaded_by'] ?? 0) === $userId;
$canApprove = ApprovalRouter::canUserApproveDocument($userId, $documentId);

if (!$isUploader && !$canApprove) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to check this document.'
    ]);
    exit;
}

$reviewedFile = trim((string) ($document['reviewed_file'] ?? ''));

/*
 * A signed copy is ready only if reviewed_file points to an
 * existing, non-empty PDF inside the application directory.
 */
$signed = false;

if ($reviewedFile !== '') {
    $appRoot = dirname(__DIR__, 2);
    $relativePath = str_replace('\\', '/', $reviewedFile);

    if (
        !str_contains($relativePath, '..') &&
        !str_starts_with($relativePath, '/') &&
        preg_match(
            '#^uploads/documents/[A-Za-z0-9_.\-/ ]+\.pdf$#i',
            $relativePath
        )
    ) {
        $fullPath = $appRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (is_file($fullPath) && filesize($fullPath) > 0) {
            $handle = fopen($fullPath, 'rb');

            if ($handle !== false) {
                $signature = fread($handle, 5);
                fclose($handle);

                $signed = ($signature === '%PDF-');
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'signed' => $signed
]);