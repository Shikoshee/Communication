<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Permission.php';

Auth::protect();

$user = Auth::getCurrentUser();

/*
|--------------------------------------------------------------------------
| DOCUMENT PERMISSIONS
|--------------------------------------------------------------------------
*/

$canView    = Permission::canView();
$canEdit    = Permission::canEdit();
$canApprove = Permission::canApprove();
$canDelete  = Permission::canDelete();
$canShare   = Permission::canShare();

$userId = (int)($user['id'] ?? 0);
$userRole = strtolower(trim($user['role'] ?? 'user'));

/*
|--------------------------------------------------------------------------
| CURRENT USER DOCUMENT PERMISSIONS
|--------------------------------------------------------------------------
*/

$canView    = Permission::canView();
$canEdit    = Permission::canEdit();
$canApprove = Permission::canApprove();
$canDelete  = Permission::canDelete();
$canShare   = Permission::canShare();

if (!$canView) {
    die("You do not have permission to view documents.");
}
/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
|
| Documents created before the approval-status logic was introduced may
| contain NULL or empty status values.
|
| In this application, an empty status means the document is still
| waiting for approval.
|
*/

function normalizeDocumentStatus($status)
{
    $status = strtolower(trim((string)$status));

    if ($status === '') {
        return 'pending';
    }

    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return 'pending';
    }

    return $status;
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

/*
 * Documents uploaded by me
 */
$totalDocuments = (int)(
    fetchRow(
        "SELECT COUNT(*) AS total
         FROM documents
         WHERE uploaded_by = ?
         AND is_deleted = 0",
        [$userId]
    )['total'] ?? 0
);


/*
 * Documents I approved
 */
$approvedDocuments = (int)(
    fetchRow(
        "SELECT COUNT(*) AS total
         FROM documents
         WHERE reviewed_by = ?
         AND is_deleted = 0
         AND status = 'approved'",
        [$userId]
    )['total'] ?? 0
);


/*
 * My documents still pending
 */
$pendingDocuments = (int)(
    fetchRow(
        "SELECT COUNT(*) AS total
         FROM documents
         WHERE uploaded_by = ?
         AND is_deleted = 0
         AND (
             status = 'pending'
             OR status IS NULL
             OR TRIM(status) = ''
         )",
        [$userId]
    )['total'] ?? 0
);


/*
 * Documents I rejected
 */
$rejectedDocuments = (int)(
    fetchRow(
        "SELECT COUNT(*) AS total
         FROM documents
         WHERE reviewed_by = ?
         AND is_deleted = 0
         AND status = 'rejected'",
        [$userId]
    )['total'] ?? 0
);


/*
 * Documents shared by me
 */
$sharedDocuments = (int)(
    fetchRow(
        "SELECT COUNT(DISTINCT ds.document_id) AS total
         FROM document_shares ds
         INNER JOIN documents d
             ON d.id = ds.document_id
         WHERE ds.shared_by = ?
         AND d.is_deleted = 0",
        [$userId]
    )['total'] ?? 0
);


/*
 * Documents shared with me
 */
$receivedDocuments = (int)(
    fetchRow(
        "SELECT COUNT(DISTINCT ds.document_id) AS total
         FROM document_shares ds
         INNER JOIN documents d
             ON d.id = ds.document_id
         WHERE ds.user_id = ?
         AND d.is_deleted = 0",
        [$userId]
    )['total'] ?? 0
);

/*
|--------------------------------------------------------------------------
| LOAD USER-RELATED DOCUMENTS
|--------------------------------------------------------------------------
*/
$documents = fetchAll(
"
SELECT DISTINCT

    doc.*,

    d.name AS department_name,

    CONCAT(
        IFNULL(u.first_name, ''),
        ' ',
        IFNULL(u.last_name, '')
    ) AS owner_name,

    CONCAT(
        IFNULL(su.first_name, ''),
        ' ',
        IFNULL(su.last_name, '')
    ) AS shared_by_name

FROM documents doc

LEFT JOIN departments d
    ON d.id = doc.department_id

LEFT JOIN users u
    ON u.id = doc.uploaded_by

LEFT JOIN document_shares ds
    ON ds.document_id = doc.id

LEFT JOIN users su
    ON su.id = ds.shared_by

WHERE

    /* ==========================================================
       CRITICAL:
       Documents in Recycle Bin must NEVER appear here.
       ========================================================== */

    COALESCE(doc.is_deleted, 0) = 0

    AND

    (
        doc.uploaded_by = ?

        OR doc.reviewed_by = ?

        OR ds.user_id = ?

        OR ds.shared_by = ?

        OR doc.department_id = (
            SELECT department_id
            FROM users
            WHERE id = ?
            LIMIT 1
        )

        OR ? IN ('admin', 'administrator')
    )

ORDER BY

    COALESCE(
        doc.updated_at,
        doc.created_at
    ) DESC
",
[
    $userId,
    $userId,
    $userId,
    $userId,
    $userId,
    $userRole
]
);

/*
|--------------------------------------------------------------------------
| LOAD RELEVANT DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = fetchAll(
"
SELECT DISTINCT

    d.id,
    d.name

FROM departments d

INNER JOIN documents doc
    ON doc.department_id = d.id

LEFT JOIN document_shares ds
    ON ds.document_id = doc.id

WHERE

    COALESCE(doc.is_deleted, 0) = 0

    AND

    (
        doc.uploaded_by = ?
        OR doc.reviewed_by = ?
        OR ds.shared_by = ?
        OR ds.user_id = ?
    )

ORDER BY d.name
",
[
    $userId,
    $userId,
    $userId,
    $userId
]
);

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/documents.css">


<div class="page-header">

    <div>

        <h1>
            My Documents
        </h1>

        <p>
            View and manage documents related to your account.
        </p>

    </div>


    <a href="upload.php" class="upload-btn">

        <i class="fa fa-upload"></i>

        Upload Document

    </a>

</div>


<!-- ==========================================================
     SUMMARY
========================================================== -->

<div class="document-summary">


    <div class="summary-card blue">

        <i class="fa fa-file"></i>

        <div>

            <h2>
                <?= $totalDocuments ?>
            </h2>

            <p>
                My Documents
            </p>

        </div>

    </div>


    <div class="summary-card green">

        <i class="fa fa-check-circle"></i>

        <div>

            <h2>
                <?= $approvedDocuments ?>
            </h2>

            <p>
                Approved by Me
            </p>

        </div>

    </div>


    <div class="summary-card orange">

        <i class="fa fa-clock"></i>

        <div>

            <h2>
                <?= $pendingDocuments ?>
            </h2>

            <p>
                My Pending
            </p>

        </div>

    </div>


    <div class="summary-card red">

        <i class="fa fa-times-circle"></i>

        <div>

            <h2>
                <?= $rejectedDocuments ?>
            </h2>

            <p>
                Rejected by Me
            </p>

        </div>

    </div>


    <div class="summary-card purple">

        <i class="fa fa-share-alt"></i>

        <div>

            <h2>
                <?= $sharedDocuments ?>
            </h2>

            <p>
                Shared by Me
            </p>

        </div>

    </div>


    <div class="summary-card cyan">

        <i class="fa fa-inbox"></i>

        <div>

            <h2>
                <?= $receivedDocuments ?>
            </h2>

            <p>
                Shared With Me
            </p>

        </div>

    </div>

</div>


<!-- ==========================================================
     FILTERS
========================================================== -->

<div class="document-controls">


    <div class="search-box">

        <i class="fa fa-search"></i>

        <input
            type="text"
            id="documentSearch"
            placeholder="Search my documents..."
        >

    </div>


    <select id="departmentFilter">

        <option value="">
            All My Departments
        </option>

        <?php foreach($departments as $dept){ ?>

            <option
                value="<?= htmlspecialchars(
                    strtolower($dept['name']),
                    ENT_QUOTES
                ) ?>"
            >
                <?= htmlspecialchars($dept['name']) ?>
            </option>

        <?php } ?>

    </select>


    <select id="statusFilter">

        <option value="">
            All Status
        </option>

        <option value="approved">
            Approved
        </option>

        <option value="pending">
            Pending
        </option>

        <option value="rejected">
            Rejected
        </option>

    </select>

</div>


<!-- ==========================================================
     DOCUMENT TABLE
========================================================== -->

<div class="document-card">

    <table>

        <thead>

            <tr>

                <th>Document</th>
                <th>Department</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Version</th>
                <th>Date</th>
                <th>Actions</th>

            </tr>

        </thead>


        <tbody id="documentsTable">


        <?php if(empty($documents)){ ?>

            <tr>

                <td
                    colspan="7"
                    style="text-align:center;padding:30px;"
                >

                    <i class="fa fa-folder-open"></i>

                    <p>
                        No documents are related to your account yet.
                    </p>

                </td>

            </tr>

        <?php } ?>


        <?php foreach($documents as $doc){ ?>

            <?php

            /*
             * ==========================================================
             * NORMALIZE STATUS
             * ==========================================================
             *
             * This is the important fix.
             *
             * Database:
             *
             * NULL       -> pending
             * ''         -> pending
             * pending    -> pending
             * approved   -> approved
             * rejected   -> rejected
             *
             */

            $status = normalizeDocumentStatus(
                $doc['status'] ?? null
            );


            $title =
                $doc['title'] ?? 'Untitled Document';

            $department =
                $doc['department_name'] ?? 'No Department';

            $owner =
                trim($doc['owner_name'] ?? '');

            if ($owner === '') {
                $owner = '-';
            }


            /*
             * FILE TYPE
             */

            $fileType = strtolower(
                trim($doc['file_type'] ?? '')
            );


            /*
             * FILE PATH
             */

            $filePath = trim(
                $doc['file_path'] ?? ''
            );


            /*
             * EXTENSION
             */

            $parsedPath = parse_url(
                $filePath,
                PHP_URL_PATH
            );

            $fileExtension = strtolower(
                pathinfo(
                    $parsedPath ?: $filePath,
                    PATHINFO_EXTENSION
                )
            );


            /*
             * OFFICE FILES
             */

            $wordExtensions = [
                'doc',
                'docx'
            ];

            $excelExtensions = [
                'xls',
                'xlsx',
                'csv'
            ];


            $isWord = in_array(
                $fileExtension,
                $wordExtensions,
                true
            );


            $isExcel = in_array(
                $fileExtension,
                $excelExtensions,
                true
            );


            /*
             * DOCUMENT URL
             */

            if (
                preg_match(
                    '#^https?://#i',
                    $filePath
                )
            ) {

                $documentUrl = $filePath;

            } else {

                $documentUrl =
                    rtrim(APP_URL, '/') .
                    '/' .
                    ltrim($filePath, '/');

            }


            /*
             * FORCE LOCALHOST HTTP
             */

            $documentUrl = preg_replace(
                '#^https://localhost#i',
                'http://localhost',
                $documentUrl
            );


            /*
             * FILE ICON
             */

            $fileIcon = 'fa-file';


            if (
                str_contains($fileType, 'pdf') ||
                $fileExtension === 'pdf'
            ) {

                $fileIcon = 'fa-file-pdf pdf';

            } elseif (
                str_contains($fileType, 'word') ||
                str_contains($fileType, 'doc') ||
                $isWord
            ) {

                $fileIcon = 'fa-file-word word';

            } elseif (
                str_contains($fileType, 'excel') ||
                str_contains($fileType, 'sheet') ||
                str_contains($fileType, 'xls') ||
                $isExcel
            ) {

                $fileIcon = 'fa-file-excel';

            } elseif (
                str_contains($fileType, 'image') ||
                str_contains($fileType, 'jpg') ||
                str_contains($fileType, 'jpeg') ||
                str_contains($fileType, 'png')
            ) {

                $fileIcon = 'fa-file-image';

            }

            ?>


            <tr

                data-name="<?= htmlspecialchars(
                    strtolower($title),
                    ENT_QUOTES
                ) ?>"

                data-department="<?= htmlspecialchars(
                    strtolower($department),
                    ENT_QUOTES
                ) ?>"

                data-status="<?= htmlspecialchars(
                    $status,
                    ENT_QUOTES
                ) ?>"
            >


                <!-- DOCUMENT -->

                <td>

                    <i class="fa <?= $fileIcon ?>"></i>

                    <?= htmlspecialchars($title) ?>

                </td>


                <!-- DEPARTMENT -->

                <td>

                    <?= htmlspecialchars($department) ?>

                </td>


                <!-- OWNER -->

                <td>

                    <?= htmlspecialchars($owner) ?>


                    <?php if (!empty($doc['shared_by_name'])) { ?>

                        <div style="
                            font-size:12px;
                            color:#777;
                            margin-top:4px;
                        ">

                            <i class="fa fa-share"></i>

                            Shared by

                            <strong>
                                <?= htmlspecialchars(
                                    trim($doc['shared_by_name'])
                                ) ?>
                            </strong>

                        </div>

                    <?php } ?>

                </td>


                <!-- STATUS -->

                <td>

                    <?php

                    /*
                     * Status label is now guaranteed to contain
                     * pending / approved / rejected.
                     */

                    $statusLabel = ucfirst($status);

                    ?>

                    <span
                        class="status <?= htmlspecialchars(
                            $status,
                            ENT_QUOTES
                        ) ?>"
                    >

                        <?= htmlspecialchars($statusLabel) ?>

                    </span>

                </td>


                <!-- VERSION -->

                <td>

                    v<?= htmlspecialchars(
                        $doc['version'] ?? '1'
                    ) ?>

                </td>


                <!-- DATE -->

                <td>

                    <?= !empty($doc['created_at'])

                        ? date(
                            "d M Y",
                            strtotime($doc['created_at'])
                        )

                        : '-'

                    ?>

                </td>


               <!-- ACTIONS -->

<td>

    <!-- ==================================================
         VIEW / DOWNLOAD
    =================================================== -->

    <?php if ($canView && !empty($doc['file_path'])) { ?>

        <?php if ($isWord): ?>

            <a
                href="ms-word:ofe|u|<?= htmlspecialchars(
                    $documentUrl,
                    ENT_QUOTES
                ) ?>"
                class="action view"
                title="Open with Microsoft Word"
            >

                <i class="fa fa-eye"></i>

            </a>

        <?php elseif ($isExcel): ?>

            <a
                href="ms-excel:ofe|u|<?= htmlspecialchars(
                    $documentUrl,
                    ENT_QUOTES
                ) ?>"
                class="action view"
                title="Open with Microsoft Excel"
            >

                <i class="fa fa-eye"></i>

            </a>

        <?php else: ?>

            <a
                href="<?= htmlspecialchars(
                    $documentUrl,
                    ENT_QUOTES
                ) ?>"
                target="_blank"
                class="action view"
                title="View Document"
            >

                <i class="fa fa-eye"></i>

            </a>

        <?php endif; ?>


        <!-- DOWNLOAD -->

        <a
            download
            href="<?= htmlspecialchars(
                $documentUrl,
                ENT_QUOTES
            ) ?>"
            class="action download"
            title="Download Document"
        >

            <i class="fa fa-download"></i>

        </a>

    <?php } ?>


    <!-- APPROVE / REJECT -->

    <?php if (
        $canApprove &&
        $status === 'pending'
    ) { ?>

        <button
            type="button"
            class="action approve"
            title="Approve Document"
            onclick="approveDocument(
                <?= (int)$doc['id'] ?>
            )"
        >

            <i class="fa fa-check"></i>

        </button>


        <button
            type="button"
            class="action reject"
            title="Reject Document"
            onclick="rejectDocument(
                <?= (int)$doc['id'] ?>
            )"
        >

            <i class="fa fa-times"></i>

        </button>

    <?php } ?>


    <!-- SHARE -->

    <?php if ($canShare) { ?>

        <button
            type="button"
            class="action share"
            title="Share Document"
            onclick="shareDocument(
                <?= (int)$doc['id'] ?>
            )"
        >

            <i class="fa fa-share"></i>

        </button>

    <?php } ?>


    <!-- DELETE -->

    <?php if($canDelete){ ?>

<button
    type="button"
    class="action delete"
    title="Move to Recycle Bin"
    onclick="deleteDocument(
        <?= (int)$doc['id'] ?>,
        '<?= htmlspecialchars(
            $title,
            ENT_QUOTES
        ) ?>'
    )"
>
    <i class="fa fa-trash"></i>
</button>

<?php } ?>

</td>

            </tr>


        <?php } ?>


        </tbody>

    </table>

</div>


<script src="assets/js/documents.js"></script>


<?php

include "includes/footer.php";

?>