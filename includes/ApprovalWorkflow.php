<?php

class ApprovalWorkflow
{
    /**
     * Step 1: Submit to Manager for Review
     * Called when user wants to send document for approval
     */
    public static function submitToManager($documentId)
    {
        $document = fetchRow(
            "SELECT id, status FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return ['success' => false, 'message' => 'Document not found'];
        }

        // Update document status
        $result = updateData(
            'documents',
            ['status' => 'pending_manager_approval'],
            ['id' => $documentId]
        );

        if ($result['success']) {
            // Notify managers
            self::notifyManagers($documentId);
            return ['success' => true, 'message' => 'Submitted to manager for review'];
        }

        return ['success' => false, 'message' => 'Failed to submit document'];
    }

    /**
     * Step 2: Manager Reviews and Approves/Rejects
     * Called when manager makes a decision
     */
    public static function managerDecision($documentId, $managerId, $decision, $notes = '')
    {
        $document = fetchRow(
            "SELECT id FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return ['success' => false, 'message' => 'Document not found'];
        }

        // If rejected by manager, return to user
        if ($decision === 'rejected') {
            $result = updateData(
                'documents',
                [
                    'status' => 'manager_rejected',
                    'manager_id' => $managerId,
                    'manager_status' => 'rejected',
                    'manager_notes' => $notes,
                    'manager_reviewed_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $documentId]
            );

            if ($result['success']) {
                self::notifyUserRejected($documentId, 'manager', $notes);
                return ['success' => true, 'message' => 'Document rejected'];
            }
        }

        // If approved by manager, move to final approval
        if ($decision === 'approved') {
            $result = updateData(
                'documents',
                [
                    'status' => 'manager_approved',
                    'manager_id' => $managerId,
                    'manager_status' => 'approved',
                    'manager_notes' => $notes,
                    'manager_reviewed_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $documentId]
            );

            if ($result['success']) {
                return ['success' => true, 'message' => 'Manager approved. Please select final approver'];
            }
        }

        return ['success' => false, 'message' => 'Invalid decision'];
    }

    /**
     * Step 3: Manager Selects Final Approver and Sends for Final Approval
     * Called when manager selects which final approver should review
     */
    public static function submitToFinalApprover($documentId, $finalApproverId)
    {
        $document = fetchRow(
            "SELECT id, status FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return ['success' => false, 'message' => 'Document not found'];
        }

        if ($document['status'] !== 'manager_approved') {
            return ['success' => false, 'message' => 'Document must be manager approved first'];
        }

        // Verify final approver exists and has correct role
        $finalApprover = fetchRow(
            "SELECT id FROM users WHERE id = ? AND role = 'final_approver'",
            [$finalApproverId]
        );

        if (!$finalApprover) {
            return ['success' => false, 'message' => 'Invalid final approver'];
        }

        // Update document
        $result = updateData(
            'documents',
            [
                'status' => 'pending_final_approval',
                'final_approver_id' => $finalApproverId
            ],
            ['id' => $documentId]
        );

        if ($result['success']) {
            self::notifyFinalApprover($documentId, $finalApproverId);
            return ['success' => true, 'message' => 'Sent to final approver'];
        }

        return ['success' => false, 'message' => 'Failed to send to final approver'];
    }

    /**
     * Step 4: Final Approver Makes Final Decision
     * Called when final approver approves or rejects
     */
    public static function finalApproverDecision($documentId, $finalApproverId, $decision, $notes = '')
    {
        $document = fetchRow(
            "SELECT id, uploaded_by FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return ['success' => false, 'message' => 'Document not found'];
        }

        // Verify this is the assigned final approver
        $assignedApprover = fetchRow(
            "SELECT final_approver_id FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$assignedApprover || $assignedApprover['final_approver_id'] != $finalApproverId) {
            return ['success' => false, 'message' => 'You are not assigned to approve this document'];
        }

        $status = ($decision === 'approved') ? 'approved' : 'rejected';

        $result = updateData(
            'documents',
            [
                'status' => $status,
                'final_approver_status' => $decision,
                'final_approver_notes' => $notes,
                'final_approver_reviewed_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $documentId]
        );

        if ($result['success']) {
            // Notify user of final decision
            self::notifyUserFinalDecision($documentId, $decision, $notes);
            return ['success' => true, 'message' => 'Final decision recorded'];
        }

        return ['success' => false, 'message' => 'Failed to record decision'];
    }

    /**
     * Get documents for user based on their role and workflow stage
     * THIS IS THE MOST IMPORTANT METHOD - MAKES SURE EACH ROLE SEES CORRECT DOCS
     */
    public static function getDocumentsForUser($userId)
    {
        $user = fetchRow(
            "SELECT id, role, department_id FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return [];
        }

        $role = strtolower($user['role']);

        // ==========================================
        // USERS: See their own documents
        // ==========================================
        if ($role === 'user') {
            $result = fetchAll(
                "SELECT 
                    d.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS manager_name,
                    CONCAT(COALESCE(fa.first_name, ''), ' ', COALESCE(fa.last_name, '')) AS final_approver_name
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.manager_id
                 LEFT JOIN users fa ON fa.id = d.final_approver_id
                 WHERE d.uploaded_by = ?
                 ORDER BY d.created_at DESC",
                [$userId]
            );
            return $result ? $result : [];
        }

        // ==========================================
        // MANAGERS: See documents waiting for their review from their department
        // ==========================================
        if ($role === 'manager') {
            $result = fetchAll(
                "SELECT 
                    d.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS uploader_name
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 WHERE d.status = 'pending_manager_approval'
                 AND d.department_id = ?
                 ORDER BY d.created_at DESC",
                [$user['department_id']]
            );
            return $result ? $result : [];
        }

        // ==========================================
        // FINAL_APPROVER: See documents assigned to them
        // ==========================================
        if ($role === 'final_approver') {
            $result = fetchAll(
                "SELECT 
                    d.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS uploader_name,
                    CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) AS manager_name
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 LEFT JOIN users m ON m.id = d.manager_id
                 WHERE d.final_approver_id = ?
                 AND d.status = 'pending_final_approval'
                 ORDER BY d.created_at DESC",
                [$userId]
            );
            return $result ? $result : [];
        }

        return [];
    }

    /**
     * Get available final approvers
     */
    public static function getFinalApprovers()
    {
        $result = fetchAll(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS name
             FROM users
             WHERE role = 'final_approver'
             AND status = 'active'
             ORDER BY first_name, last_name"
        );
        return $result ? $result : [];
    }

    /**
     * Send notifications
     */
    private static function notifyManagers($documentId)
    {
        require_once __DIR__ . '/notifications.php';

        $document = fetchRow(
            "SELECT title, department_id FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return;
        }

        // Get all managers in this department
        $managers = fetchAll(
            "SELECT id FROM users WHERE role = 'manager' AND department_id = ? AND status = 'active'",
            [$document['department_id']]
        );

        if ($managers) {
            foreach ($managers as $manager) {
                createNotification(
                    $manager['id'],
                    'Document Requires Review',
                    'Document "' . htmlspecialchars($document['title']) . '" requires your review',
                    'approval',
                    $documentId
                );
            }
        }
    }

    private static function notifyFinalApprover($documentId, $finalApproverId)
    {
        require_once __DIR__ . '/notifications.php';

        $document = fetchRow(
            "SELECT title FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return;
        }

        createNotification(
            $finalApproverId,
            'Document Requires Final Approval',
            'Document "' . htmlspecialchars($document['title']) . '" requires your final approval',
            'approval',
            $documentId
        );
    }

    private static function notifyUserRejected($documentId, $rejectedBy, $notes)
    {
        require_once __DIR__ . '/notifications.php';

        $document = fetchRow(
            "SELECT title, uploaded_by FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return;
        }

        $message = 'Document "' . htmlspecialchars($document['title']) . '" was rejected by ' . $rejectedBy;
        if ($notes) {
            $message .= ': ' . htmlspecialchars($notes);
        }

        createNotification(
            $document['uploaded_by'],
            'Document Rejected',
            $message,
            'approval',
            $documentId
        );
    }

    private static function notifyUserFinalDecision($documentId, $decision, $notes)
    {
        require_once __DIR__ . '/notifications.php';

        $document = fetchRow(
            "SELECT title, uploaded_by FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return;
        }

        $status = ($decision === 'approved') ? 'APPROVED' : 'REJECTED';
        $message = 'Document "' . htmlspecialchars($document['title']) . '" has been ' . $status;
        if ($notes) {
            $message .= ': ' . htmlspecialchars($notes);
        }

        createNotification(
            $document['uploaded_by'],
            'Document ' . ucfirst($decision),
            $message,
            'approval',
            $documentId
        );
    }
}

?>