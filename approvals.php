<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Permission.php';

Auth::protect();

$user = Auth::getCurrentUser();

$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim(
        (string)($user['role'] ?? 'user')
    )
);


/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
*/

$isAdmin = in_array(
    $userRole,
    [
        'admin',
        'administrator'
    ],
    true
);

$isManager = (
    $userRole === 'manager'
    ||
    str_contains($userRole, 'manager')
);


/*
|--------------------------------------------------------------------------
| APPROVAL PERMISSION
|--------------------------------------------------------------------------
|
| Approval access is controlled by Permission::canApprove().
|
| Admin:
|     Always has approval permission.
|
| Manager:
|     Depends on can_approve unless admin.
|
| Normal user:
|     Can access this page when can_approve = 1.
|
*/

$canApprove = Permission::canApprove();


if (!$canApprove) {

    die(
        "You do not have permission to approve documents."
    );

}


/*
|--------------------------------------------------------------------------
| USER DEPARTMENT
|--------------------------------------------------------------------------
*/

$userDepartmentId = Permission::getDepartmentId();


/*
|--------------------------------------------------------------------------
| PENDING STATUS
|--------------------------------------------------------------------------
|
| Existing documents may contain:
|
|     pending
|     NULL
|     ''
|
| All are treated as pending.
|
*/

$pendingCondition = "
(
    d.status = 'pending'
    OR d.status IS NULL
    OR TRIM(d.status) = ''
)
";


/*
|--------------------------------------------------------------------------
| LOAD PENDING DOCUMENTS
|--------------------------------------------------------------------------
|
| ADMINISTRATOR:
|     Can see every pending document.
|
| OTHER USERS WITH APPROVAL PERMISSION:
|     Can see documents belonging to their department
|     or documents specifically shared with them.
|
| This allows a normal user who has been granted approval
| permission to participate in the approval flow.
|
*/

if ($isAdmin) {

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
                IFNULL(u.first_name, ''),
                ' ',
                IFNULL(u.last_name, '')
            ) AS owner_name

        FROM documents d

        LEFT JOIN departments dept
            ON dept.id = d.department_id

        LEFT JOIN users u
            ON u.id = d.uploaded_by

        WHERE

            COALESCE(d.is_deleted, 0) = 0

            AND

            $pendingCondition

        ORDER BY

            d.created_at DESC
        "

    );

} else {

    /*
     * USER WITH APPROVAL PERMISSION
     *
     * Managers and normal users follow the same
     * department/share visibility rule.
     */

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
                IFNULL(u.first_name, ''),
                ' ',
                IFNULL(u.last_name, '')
            ) AS owner_name

        FROM documents d

        LEFT JOIN departments dept
            ON dept.id = d.department_id

        LEFT JOIN users u
            ON u.id = d.uploaded_by

        LEFT JOIN document_shares ds
            ON ds.document_id = d.id
            AND ds.user_id = ?

        WHERE

            COALESCE(d.is_deleted, 0) = 0

            AND

            $pendingCondition

            AND

            (
                d.department_id = ?

                OR

                ds.user_id = ?
            )

        ORDER BY

            d.created_at DESC
        ",

        [
            $userId,
            $userDepartmentId,
            $userId
        ]

    );

}


/*
|--------------------------------------------------------------------------
| SAFETY
|--------------------------------------------------------------------------
*/

if (!is_array($pendingDocuments)) {

    $pendingDocuments = [];

}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/


/*
 * PENDING
 */

$pendingCount = count(
    $pendingDocuments
);


/*
|--------------------------------------------------------------------------
| APPROVED / REJECTED COUNTS
|--------------------------------------------------------------------------
*/

if ($isAdmin) {

    $approvedCount = (int)(
        fetchRow(

            "
            SELECT COUNT(*) AS total

            FROM documents d

            WHERE

                COALESCE(d.is_deleted, 0) = 0

                AND d.status = 'approved'
            "

        )['total'] ?? 0
    );


    $rejectedCount = (int)(
        fetchRow(

            "
            SELECT COUNT(*) AS total

            FROM documents d

            WHERE

                COALESCE(d.is_deleted, 0) = 0

                AND d.status = 'rejected'
            "

        )['total'] ?? 0
    );

} else {

    $approvedCount = (int)(
        fetchRow(

            "
            SELECT COUNT(DISTINCT d.id) AS total

            FROM documents d

            LEFT JOIN document_shares ds
                ON ds.document_id = d.id
                AND ds.user_id = ?

            WHERE

                COALESCE(d.is_deleted, 0) = 0

                AND d.status = 'approved'

                AND

                (
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


    $rejectedCount = (int)(
        fetchRow(

            "
            SELECT COUNT(DISTINCT d.id) AS total

            FROM documents d

            LEFT JOIN document_shares ds
                ON ds.document_id = d.id
                AND ds.user_id = ?

            WHERE

                COALESCE(d.is_deleted, 0) = 0

                AND d.status = 'rejected'

                AND

                (
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
                IFNULL(u.first_name, ''),
                ' ',
                IFNULL(u.last_name, '')
            ) AS reviewer

        FROM documents d

        LEFT JOIN users u
            ON u.id = d.reviewed_by

        WHERE

            COALESCE(d.is_deleted, 0) = 0

            AND d.status IN (
                'approved',
                'rejected'
            )

        ORDER BY

            d.reviewed_at DESC

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
                IFNULL(u.first_name, ''),
                ' ',
                IFNULL(u.last_name, '')
            ) AS reviewer

        FROM documents d

        LEFT JOIN users u
            ON u.id = d.reviewed_by

        LEFT JOIN document_shares ds
            ON ds.document_id = d.id
            AND ds.user_id = ?

        WHERE

            COALESCE(d.is_deleted, 0) = 0

            AND d.status IN (
                'approved',
                'rejected'
            )

            AND

            (
                d.department_id = ?
                OR ds.user_id = ?
            )

        ORDER BY

            d.reviewed_at DESC

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
| PAGE
|--------------------------------------------------------------------------
*/

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link
    rel="stylesheet"
    href="assets/css/approvals.css"
>

<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">


<div>

    <h1>
        Document Approvals
    </h1>

    <p>
        Review and approve documents submitted by departments.
    </p>

</div>


</div>

<!-- ==========================================================
     SUMMARY CARDS
========================================================== -->

<div class="approval-cards">


<!-- PENDING -->

<div class="approval-card pending-card">

    <i class="fa fa-clock"></i>

    <div>

        <h2>
            <?= (int)$pendingCount ?>
        </h2>

        <p>
            Pending Review
        </p>

    </div>

</div>


<!-- APPROVED -->

<div class="approval-card approved-card">

    <i class="fa fa-check-circle"></i>

    <div>

        <h2>
            <?= (int)$approvedCount ?>
        </h2>

        <p>
            Approved Documents
        </p>

    </div>

</div>


<!-- REJECTED -->

<div class="approval-card rejected-card">

    <i class="fa fa-times-circle"></i>

    <div>

        <h2>
            <?= (int)$rejectedCount ?>
        </h2>

        <p>
            Rejected Documents
        </p>

    </div>

</div>


</div>

<!-- ==========================================================
     PENDING APPROVALS
========================================================== -->

<div class="approval-container">

<h3>
    Pending Approval Requests
</h3>


<table>

    <thead>

        <tr>

            <th>
                Document
            </th>

            <th>
                Department
            </th>

            <th>
                Submitted By
            </th>

            <th>
                Date Submitted
            </th>

            <th>
                Status
            </th>

            <th>
                Actions
            </th>

        </tr>

    </thead>


    <tbody>


    <?php if (!empty($pendingDocuments)) { ?>


        <?php foreach ($pendingDocuments as $doc) { ?>


            <?php

            $fileType = strtolower(
                trim(
                    $doc['file_type'] ?? ''
                )
            );


            $title =
                $doc['title'] ??
                'Untitled Document';


            $department =
                $doc['department_name'] ??
                'No Department';


            $owner =
                trim(
                    $doc['owner_name'] ??
                    ''
                );


            if ($owner === '') {

                $owner = 'Unknown';

            }


            /*
             * FILE ICON
             */

            $fileIcon = 'fa-file';


            if (
                str_contains(
                    $fileType,
                    'pdf'
                )
            ) {

                $fileIcon =
                    'fa-file-pdf pdf-icon';

            } elseif (
                str_contains(
                    $fileType,
                    'word'
                )
                ||
                str_contains(
                    $fileType,
                    'doc'
                )
            ) {

                $fileIcon =
                    'fa-file-word word-icon';

            } elseif (
                str_contains(
                    $fileType,
                    'excel'
                )
                ||
                str_contains(
                    $fileType,
                    'sheet'
                )
                ||
                str_contains(
                    $fileType,
                    'xls'
                )
            ) {

                $fileIcon =
                    'fa-file-excel';

            } elseif (
                str_contains(
                    $fileType,
                    'image'
                )
            ) {

                $fileIcon =
                    'fa-file-image';

            }

            ?>


            <tr>


                <!-- DOCUMENT -->

                <td>

                    <i class="fa <?= $fileIcon ?>"></i>

                    <?= htmlspecialchars(
                        $title
                    ) ?>

                </td>


                <!-- DEPARTMENT -->

                <td>

                    <?= htmlspecialchars(
                        $department
                    ) ?>

                </td>


                <!-- OWNER -->

                <td>

                    <?= htmlspecialchars(
                        $owner
                    ) ?>

                </td>


                <!-- DATE -->

                <td>

                    <?= !empty(
                        $doc['created_at']
                    )

                        ? date(
                            "d M Y",
                            strtotime(
                                $doc['created_at']
                            )
                        )

                        : '-'

                    ?>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="approval-status pending"
                    >

                        Pending

                    </span>

                </td>


                <!-- ACTIONS -->

                <td>


                    <button
                        type="button"
                        class="review-btn"
                        onclick="reviewDocument(
                            <?= (int)$doc['id'] ?>
                        )"
                    >

                        <i class="fa fa-eye"></i>

                        Review

                    </button>


                    <button
                        type="button"
                        class="reject-btn"
                        onclick="rejectDocument(
                            <?= (int)$doc['id'] ?>
                        )"
                    >

                        <i class="fa fa-times"></i>

                        Reject

                    </button>


                </td>


            </tr>


        <?php } ?>


    <?php } else { ?>


        <tr>

            <td
                colspan="6"
                style="
                    text-align:center;
                    padding:30px;
                "
            >

                <i
                    class="fa fa-check-circle"
                    style="font-size:30px;"
                ></i>

                <p>
                    No pending approval requests.
                </p>

            </td>

        </tr>


    <?php } ?>


    </tbody>

</table>


</div>

<!-- ==========================================================
     APPROVAL HISTORY
========================================================== -->

<div class="history-container">


<h3>
    Approval History
</h3>


<?php if (!empty($history)) { ?>


    <?php foreach ($history as $item) { ?>


        <div class="history-item">


            <div
                class="history-icon
                <?= htmlspecialchars(
                    $item['status'] ?? ''
                ) ?>"
            >

                <?php if (
                    ($item['status'] ?? '') === 'approved'
                ) { ?>

                    <i class="fa fa-check"></i>

                <?php } else { ?>

                    <i class="fa fa-times"></i>

                <?php } ?>

            </div>


            <div>


                <h4>

                    <?= htmlspecialchars(
                        $item['title'] ?? ''
                    ) ?>

                    <?= ucfirst(
                        $item['status'] ?? ''
                    ) ?>

                </h4>


                <?php if (
                    !empty(
                        $item['reviewed_file']
                    )
                ) { ?>

                    <p>

                        <a
                            href="<?= htmlspecialchars(
                                $item['reviewed_file'],
                                ENT_QUOTES
                            ) ?>"
                            target="_blank"
                        >

                            <i
                                class="fa fa-file-signature"
                            ></i>

                            View Signed Copy

                        </a>

                    </p>

                <?php } ?>


                <p>

                    <?= ucfirst(
                        $item['status'] ?? ''
                    ) ?>

                    by

                    <strong>

                        <?= !empty(
                            $item['reviewer']
                        )

                            ? htmlspecialchars(
                                $item['reviewer']
                            )

                            : 'Unknown'

                        ?>

                    </strong>

                    on

                    <?= !empty(
                        $item['reviewed_at']
                    )

                        ? date(
                            "d M Y H:i",
                            strtotime(
                                $item['reviewed_at']
                            )
                        )

                        : 'Not recorded'

                    ?>

                </p>


            </div>


        </div>


    <?php } ?>


<?php } else { ?>


    <p>
        No approval history available.
    </p>


<?php } ?>


</div>

<script src="assets/js/approvals.js"></script>

<?php

include "includes/footer.php";

?>
