<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/ApprovalWorkflow.php';

Auth::protect();

$user = Auth::getCurrentUser();

// Only final approvers can access
if (strtolower($user['role']) !== 'final_approver') {
    die("Access denied");
}

$id = (int)($_GET['id'] ?? 0);

$document = fetchRow(
    "SELECT d.*, 
            CONCAT(u.first_name, ' ', u.last_name) AS uploader,
            CONCAT(m.first_name, ' ', m.last_name) AS manager_name
     FROM documents d
     LEFT JOIN users u ON u.id = d.uploaded_by
     LEFT JOIN users m ON m.id = d.manager_id
     WHERE d.id = ? AND d.final_approver_id = ?",
    [$id, $user['id']]
);

if (!$document) {
    die("Document not found or not assigned to you");
}

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<div class="page-header">
    <h1>Final Approval</h1>
    <p>Make final decision on document</p>
</div>

<div class="review-container">

    <div class="document-info">
        <h2><?= htmlspecialchars($document['title']) ?></h2>
        <p><strong>Uploaded by:</strong> <?= htmlspecialchars($document['uploader']) ?></p>
        <p><strong>Reviewed by Manager:</strong> <?= htmlspecialchars($document['manager_name']) ?></p>
        
        <?php if ($document['manager_notes']): ?>
        <div class="manager-notes">
            <h4>Manager's Notes:</h4>
            <p><?= htmlspecialchars($document['manager_notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="review-actions">

        <div class="action-form">
            <h3>Final Decision</h3>

            <form id="finalApprovalForm">
                <input type="hidden" name="document_id" value="<?= $document['id'] ?>">

                <div class="form-group">
                    <label>
                        <input type="radio" name="decision" value="approved" required>
                        <span>APPROVE - Document is final approved</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="radio" name="decision" value="rejected" required>
                        <span>REJECT - Return document for revisions</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Decision Notes:</label>
                    <textarea name="notes" placeholder="Add notes for the record" required></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    Submit Final Decision
                </button>
            </form>
        </div>

    </div>

</div>

<script>
document.getElementById('finalApprovalForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    const response = await fetch('includes/api/final-approval.php', {
        method: 'POST',
        body: formData
    });

    const data = await response.json();

    if (data.success) {
        alert('Final decision submitted');
        window.location.href = 'approvals.php';
    } else {
        alert('Error: ' + data.message);
    }
});
</script>

<?php include "includes/footer.php"; ?>