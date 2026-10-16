
<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Permission.php';
require_once __DIR__ . '/includes/ApprovalRouter.php';

Auth::protect();

$user = Auth::getCurrentUser();

$userId = (int) ($user['id'] ?? 0);
$userRole = strtolower(trim((string) ($user['role'] ?? 'user')));

if ($userId <= 0) {
    http_response_code(401);
    exit('Invalid session. Please sign in again.');
}

/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
*/

$isAdmin = in_array(
    $userRole,
    ['admin', 'administrator'],
    true
);

$isManager = ($userRole === 'manager');

/*
|--------------------------------------------------------------------------
| APPROVAL PERMISSION
|--------------------------------------------------------------------------
*/

$canApprove = Permission::canApprove();

if (!$canApprove) {
    http_response_code(403);
    exit('You do not have permission to approve documents.');
}

$userDepartmentId = (int) (
    Permission::getDepartmentId() ?? 0
);

/*
|--------------------------------------------------------------------------
| PENDING STATUSES
|--------------------------------------------------------------------------
*/

$pendingStatuses = [
    'pending',
    'pending_manager_approval',
    'pending_final_approval'
];

/*
|--------------------------------------------------------------------------
| LOAD PENDING DOCUMENTS
|--------------------------------------------------------------------------
|
| Administrators and managers use ApprovalRouter.
|
| Other users with approval permission can see documents in their
| department or documents explicitly shared with them.
|
| All paths exclude deleted documents.
|
*/

$pendingDocuments = [];

if ($isAdmin || $isManager) {

    $pendingDocuments =
        ApprovalRouter::getPendingDocumentsForUser($userId);

} else {

    $pendingDocuments = fetchAll(
        "
        SELECT DISTINCT
            d.id,
            d.title,
            d.file_type,
            d.file_path,
            d.status,
            d.created_at,
            d.department_id,
            d.uploaded_by,
            dept.name AS department_name,
            CONCAT(
                COALESCE(u.first_name, ''),
                ' ',
                COALESCE(u.last_name, '')
            ) AS owner_name

        FROM documents d

        LEFT JOIN departments dept
            ON dept.id = d.department_id

        LEFT JOIN users u
            ON u.id = d.uploaded_by

        LEFT JOIN document_shares ds
            ON ds.document_id = d.id
           AND ds.user_id = ?

        WHERE COALESCE(d.is_deleted, 0) = 0

          AND LOWER(TRIM(COALESCE(d.status, ''))) IN (
              '',
              'pending',
              'pending_manager_approval',
              'pending_final_approval'
          )

          AND (
              d.department_id = ?
              OR ds.user_id = ?
          )

        ORDER BY d.created_at DESC, d.id DESC
        ",
        [
            $userId,
            $userDepartmentId,
            $userId
        ]
    );
}

if (!is_array($pendingDocuments)) {
    $pendingDocuments = [];
}

/*
|--------------------------------------------------------------------------
| NORMALIZE PENDING DOCUMENTS
|--------------------------------------------------------------------------
*/

foreach ($pendingDocuments as &$doc) {
    $doc['id'] = (int) ($doc['id'] ?? 0);
    $doc['title'] = $doc['title'] ?? 'Untitled Document';
    $doc['file_type'] = $doc['file_type'] ?? '';
    $doc['file_path'] = $doc['file_path'] ?? '';
    $doc['status'] = strtolower(
        trim((string) ($doc['status'] ?? 'pending'))
    );
    $doc['department_name'] =
        $doc['department_name'] ?? 'No Department';

    $doc['owner_name'] = trim(
        (string) ($doc['owner_name'] ?? '')
    );

    if ($doc['owner_name'] === '') {
        $doc['owner_name'] = 'Unknown';
    }
}
unset($doc);

/*
|--------------------------------------------------------------------------
| REMOVE DUPLICATES AND INVALID STATUSES
|--------------------------------------------------------------------------
*/

$uniqueDocuments = [];

foreach ($pendingDocuments as $doc) {
    $documentId = (int) ($doc['id'] ?? 0);
    $status = strtolower(
        trim((string) ($doc['status'] ?? ''))
    );

    if ($documentId <= 0) {
        continue;
    }

    if (!in_array(
        $status,
        ['', 'pending', 'pending_manager_approval', 'pending_final_approval'],
        true
    )) {
        continue;
    }

    $uniqueDocuments[$documentId] = $doc;
}

$pendingDocuments = array_values($uniqueDocuments);

/*
|--------------------------------------------------------------------------
| PENDING COUNT
|--------------------------------------------------------------------------
*/

$pendingCount = count($pendingDocuments);

/*
|--------------------------------------------------------------------------
| APPROVED / REJECTED COUNTS
|--------------------------------------------------------------------------
*/

if ($isAdmin || $isManager) {

    $stats = ApprovalRouter::getApprovalStats($userId);

    $approvedCount = (int) ($stats['approved'] ?? 0);
    $rejectedCount = (int) ($stats['rejected'] ?? 0);

} else {

    $approvedCount = (int) (
        fetchRow(
            "
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d

            LEFT JOIN document_shares ds
                ON ds.document_id = d.id
               AND ds.user_id = ?

            WHERE COALESCE(d.is_deleted, 0) = 0
              AND LOWER(TRIM(COALESCE(d.status, ''))) = 'approved'
              AND (
                  d.department_id = ?
                  OR ds.user_id = ?
              )
            ",
            [
                $userId,
                $userDepartmentId,
                $userId
            ]
        )['total'] ?? 0
    );

    $rejectedCount = (int) (
        fetchRow(
            "
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d

            LEFT JOIN document_shares ds
                ON ds.document_id = d.id
               AND ds.user_id = ?

            WHERE COALESCE(d.is_deleted, 0) = 0
              AND LOWER(TRIM(COALESCE(d.status, ''))) = 'rejected'
              AND (
                  d.department_id = ?
                  OR ds.user_id = ?
              )
            ",
            [
                $userId,
                $userDepartmentId,
                $userId
            ]
        )['total'] ?? 0
    );
}

/*
|--------------------------------------------------------------------------
| APPROVAL HISTORY
|--------------------------------------------------------------------------
*/

if ($isAdmin) {

    $history = fetchAll(
        "
        SELECT
            d.title,
            d.status,
            d.reviewed_at,
            d.reviewed_file,
            CONCAT(
                COALESCE(u.first_name, ''),
                ' ',
                COALESCE(u.last_name, '')
            ) AS reviewer

        FROM documents d

        LEFT JOIN users u
            ON u.id = d.reviewed_by

        WHERE COALESCE(d.is_deleted, 0) = 0
          AND LOWER(TRIM(COALESCE(d.status, '')))
              IN ('approved', 'rejected')

        ORDER BY d.reviewed_at DESC, d.id DESC
        LIMIT 10
        "
    );

} else {

    $history = fetchAll(
        "
        SELECT DISTINCT
            d.title,
            d.status,
            d.reviewed_at,
            d.reviewed_file,
            CONCAT(
                COALESCE(u.first_name, ''),
                ' ',
                COALESCE(u.last_name, '')
            ) AS reviewer

        FROM documents d

        LEFT JOIN users u
            ON u.id = d.reviewed_by

        LEFT JOIN document_shares ds
            ON ds.document_id = d.id
           AND ds.user_id = ?

        WHERE COALESCE(d.is_deleted, 0) = 0
          AND LOWER(TRIM(COALESCE(d.status, '')))
              IN ('approved', 'rejected')
          AND (
              d.department_id = ?
              OR ds.user_id = ?
          )

        ORDER BY d.reviewed_at DESC, d.id DESC
        LIMIT 10
        ",
        [
            $userId,
            $userDepartmentId,
            $userId
        ]
    );
}

if (!is_array($history)) {
    $history = [];
}

/*
|--------------------------------------------------------------------------
| HELPER: ESCAPE HTML
|--------------------------------------------------------------------------
*/

function approvalEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| PAGE LAYOUT
|--------------------------------------------------------------------------
*/

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/navbar.php';

?>

<link
    rel="stylesheet"
    href="assets/css/approvals.css"
>

<div class="page-header">
    <div>
        <h1>Document Approvals</h1>
        <p>Review and approve documents submitted by departments.</p>
    </div>
</div>

<!-- SUMMARY CARDS -->

<div class="approval-cards">

    <div class="approval-card pending-card">
        <i class="fa fa-clock"></i>
        <div>
            <h2><?= $pendingCount ?></h2>
            <p>Pending Review</p>
        </div>
    </div>

    <div class="approval-card approved-card">
        <i class="fa fa-check-circle"></i>
        <div>
            <h2><?= $approvedCount ?></h2>
            <p>Approved Documents</p>
        </div>
    </div>

    <div class="approval-card rejected-card">
        <i class="fa fa-times-circle"></i>
        <div>
            <h2><?= $rejectedCount ?></h2>
            <p>Rejected Documents</p>
        </div>
    </div>

</div>

<!-- PENDING APPROVALS -->

<div class="approval-container">

    <h3>Pending Approval Requests</h3>

    <table>
        <thead>
            <tr>
                <th>Document</th>
                <th>Department</th>
                <th>Submitted By</th>
                <th>Date Submitted</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

        <?php if (!empty($pendingDocuments)): ?>

            <?php foreach ($pendingDocuments as $doc): ?>

                <?php
                $fileType = strtolower(
                    trim((string) ($doc['file_type'] ?? ''))
                );

                $title = $doc['title'] ?? 'Untitled Document';

                $department =
                    $doc['department_name'] ?? 'No Department';

                $owner = trim(
                    (string) ($doc['owner_name'] ?? '')
                );

                if ($owner === '') {
                    $owner = 'Unknown';
                }

                $fileIcon = 'fa-file';

                if (str_contains($fileType, 'pdf')) {
                    $fileIcon = 'fa-file-pdf pdf-icon';
                } elseif (
                    str_contains($fileType, 'word') ||
                    str_contains($fileType, 'doc')
                ) {
                    $fileIcon = 'fa-file-word word-icon';
                } elseif (
                    str_contains($fileType, 'excel') ||
                    str_contains($fileType, 'sheet') ||
                    str_contains($fileType, 'xls')
                ) {
                    $fileIcon = 'fa-file-excel';
                } elseif (str_contains($fileType, 'image')) {
                    $fileIcon = 'fa-file-image';
                }

                $status = strtolower(
                    trim((string) ($doc['status'] ?? 'pending'))
                );

                switch ($status) {
                    case 'pending_manager_approval':
                        $statusLabel = 'Pending Manager Approval';
                        break;

                    case 'pending_final_approval':
                        $statusLabel = 'Pending Final Approval';
                        break;

                    default:
                        $statusLabel = 'Pending';
                        break;
                }

                $documentId = (int) $doc['id'];

                $createdAt = $doc['created_at'] ?? null;
                ?>

                <tr>
                    <td>
                        <i class="fa <?= approvalEscape($fileIcon) ?>"></i>
                        <?= approvalEscape($title) ?>
                    </td>

                    <td><?= approvalEscape($department) ?></td>

                    <td><?= approvalEscape($owner) ?></td>

                    <td>
                        <?php if (!empty($createdAt) && strtotime($createdAt) !== false): ?>
                            <?= date('d M Y', strtotime($createdAt)) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="approval-status pending">
                            <?= approvalEscape($statusLabel) ?>
                        </span>
                    </td>

                    <td>
                        <button
                            type="button"
                            class="review-btn"
                            onclick="reviewDocument(<?= $documentId ?>)"
                        >
                            <i class="fa fa-eye"></i>
                            Review
                        </button>

                        <button
                            type="button"
                            class="reject-btn"
                            onclick="rejectDocument(<?= $documentId ?>)"
                        >
                            <i class="fa fa-times"></i>
                            Reject
                        </button>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="6" style="text-align:center;padding:30px;">
                    <i class="fa fa-check-circle" style="font-size:30px;"></i>
                    <p>No pending approval requests.</p>
                </td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

</div>

<!-- APPROVAL HISTORY -->

<div class="history-container">

    <h3>Approval History</h3>

    <?php if (!empty($history)): ?>

        <?php foreach ($history as $item): ?>

            <?php
            $historyStatus = strtolower(
                trim((string) ($item['status'] ?? ''))
            );

            $reviewer = trim(
                (string) ($item['reviewer'] ?? '')
            );
            ?>

            <div class="history-item">

                <div class="history-icon <?= approvalEscape($historyStatus) ?>">
                    <?php if ($historyStatus === 'approved'): ?>
                        <i class="fa fa-check"></i>
                    <?php else: ?>
                        <i class="fa fa-times"></i>
                    <?php endif; ?>
                </div>

                <div>
                    <h4>
                        <?= approvalEscape($item['title'] ?? 'Untitled Document') ?>
                        <?= approvalEscape(ucfirst($historyStatus)) ?>
                    </h4>

                    <?php if (!empty($item['reviewed_file'])): ?>
                        <p>
                            <a
                                href="<?= approvalEscape($item['reviewed_file']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="fa fa-file-signature"></i>
                                View Signed Copy
                            </a>
                        </p>
                    <?php endif; ?>

                    <p>
                        <?= approvalEscape(ucfirst($historyStatus)) ?>
                        by
                        <strong>
                            <?= approvalEscape($reviewer !== '' ? $reviewer : 'Unknown') ?>
                        </strong>
                        on

                        <?php
                        $reviewedAt = $item['reviewed_at'] ?? null;
                        ?>

                        <?php if (!empty($reviewedAt) && strtotime($reviewedAt) !== false): ?>
                            <?= date('d M Y H:i', strtotime($reviewedAt)) ?>
                        <?php else: ?>
                            Not recorded
                        <?php endif; ?>
                    </p>
                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>No approval history available.</p>

    <?php endif; ?>

</div>

<script src="assets/js/approvals.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>

