<?php

class ApprovalNotification
{
    /**
     * Send approval notifications to all relevant approvers
     */
    public static function notifyApprovers($documentId)
    {
        require_once __DIR__ . '/notifications.php';
        require_once __DIR__ . '/ApprovalRouter.php';

        $document = fetchRow(
            "SELECT title FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return false;
        }

        // Get all users who should approve
        $approvers = ApprovalRouter::getApproversForDocument($documentId);

        // Send notification to each approver
        foreach ($approvers as $approverId) {
            createNotification(
                $approverId,
                'Document Requires Approval',
                'Document "' . htmlspecialchars($document['title']) . '" requires your approval',
                'approval',
                $documentId,
                null
            );
        }

        return true;
    }

    /**
     * Send approval decision notification (approved/rejected)
     */
    public static function notifyUploader($documentId, $decision, $reviewerName)
    {
        require_once __DIR__ . '/notifications.php';

        $document = fetchRow(
            "SELECT title, uploaded_by FROM documents WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return false;
        }

        $message = "Document \"" . htmlspecialchars($document['title']) . 
                   "\" has been " . strtoupper($decision) . " by " . 
                   htmlspecialchars($reviewerName);

        createNotification(
            $document['uploaded_by'],
            'Document ' . ucfirst($decision),
            $message,
            'approval',
            $documentId,
            null
        );

        return true;
    }
}

?>