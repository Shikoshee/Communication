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
$decision = $_POST['decision'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$documentId || !in_array($decision, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$result = ApprovalWorkflow::managerDecision($documentId, $user['id'], $decision, $notes);

header('Content-Type: application/json');
echo json_encode($result);

?>