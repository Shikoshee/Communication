<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/ApprovalWorkflow.php';

Auth::protect();

$user = Auth::getCurrentUser();

// Only managers can access
if (strtolower($user['role']) !== 'manager') {
    die("Access denied");
}

$id = (int)($_GET['id'] ?? 0);

$document = fetchRow(
    "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS uploader
     FROM documents d
     LEFT JOIN users u ON u.id = d.uploaded_by
     WHERE d.id = ?",
    [$id]
);

if (!$document) {
    die("Document not found");
}

// Verify manager can review this document
if ($document['department_id'] != $user['department_id']) {
    die("You can only review documents from your department");
}

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<div class="page-header">
    <h1>Manager Review</h1>
    <p>Review and approve/reject document</p>
</div>

<div class="review-container">

    <div class="document-info">
        <h2><?= htmlspecialchars($document['title']) ?></h2>
        <p><strong>Uploaded by:</strong> <?= htmlspecialchars($document['uploader']) ?></p>
        <p><strong>Date:</strong> <?= date('d M Y', strtotime($document['created_at'])) ?></p>
    </div>

    <div class="review-actions">

        <div class="action-form">
            <h3>Your Decision</h3>

            <form id="reviewForm">
                <input type="hidden" name="document_id" value="<?= $document['id'] ?>">

                <div class="form-group">
                    <label>
                        <input type="radio" name="decision" value="approved" required>
                        <span>Approve this document</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="radio" name="decision" value="rejected" required>
                        <span>Reject this document</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Notes (optional):</label>
                    <textarea name="notes" placeholder="Add any notes for the approver"></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    Submit Decision
                </button>
            </form>
        </div>

        <!-- Document preview would go here -->
        <div class="document-preview">
            <p>Document preview or download link here</p>
        </div>

    </div>

</div>

<script>
document.getElementById('reviewForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    const response = await fetch('includes/api/manager-decision.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();

    if (data.success) {
        alert('Decision submitted successfully');
        window.location.href = 'approvals.php';
    } else {
        alert('Error: ' + data.message);
    }
});
</script>

<?php include "includes/footer.php"; ?>