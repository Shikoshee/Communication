<?php

require_once '../config.php';
require_once '../auth.php';
require_once '../ApprovalRouter.php';
require_once '../ApprovalNotification.php';

Auth::protect();

$user = Auth::getCurrentUser();
$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
$decision = isset($_POST['decision']) ? $_POST['decision'] : '';

if (!$documentId || !in_array($decision, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Check permission
if (!ApprovalRouter::canUserApproveDocument($user['id'], $documentId)) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to approve this document'
    ]);
    exit;
}

// Update document
$result = updateData(
    'documents',
    [
        'status' => $decision,
        'reviewed_by' => $user['id'],
        'reviewed_at' => date('Y-m-d H:i:s')
    ],
    ['id' => $documentId]
);

if ($result['success']) {
    // Notify uploader
    $reviewerName = $user['first_name'] . ' ' . $user['last_name'];
    ApprovalNotification::notifyUploader($documentId, $decision, $reviewerName);

    echo json_encode([
        'success' => true,
        'message' => 'Document ' . $decision . ' successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to ' . $decision . ' document'
    ]);
}

?>