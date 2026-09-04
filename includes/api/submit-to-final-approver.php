<?php

require_once '../config.php';
require_once '../auth.php';
require_once '../ApprovalWorkflow.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (strtolower($user['role']) !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$documentId = (int)($_POST['document_id'] ?? 0);
$finalApproverId = (int)($_POST['final_approver_id'] ?? 0);

if (!$documentId || !$finalApproverId) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$result = ApprovalWorkflow::submitToFinalApprover($documentId, $finalApproverId);

header('Content-Type: application/json');
echo json_encode($result);

?>