<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Permission.php';
Auth::protect();

if (!Permission::canApprove()) {
    die("You do not have permission to approve documents");
}
$user = Auth::getCurrentUser();

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";


// ==============================
// APPROVAL STATISTICS
// ==============================

$pendingCount = countRows("
SELECT id
FROM documents
WHERE status='pending'
");

$approvedCount = countRows("
SELECT id
FROM documents
WHERE status='approved'
");

$rejectedCount = countRows("
SELECT id
FROM documents
WHERE status='rejected'
");



// ==============================
// LOAD PENDING DOCUMENTS
// ==============================

$pendingDocuments = fetchAll("

SELECT

d.id,
d.title,
d.file_type,
d.created_at,

dept.name AS department_name,

CONCAT(
u.first_name,
' ',
u.last_name
) AS owner_name

FROM documents d

LEFT JOIN departments dept
ON dept.id=d.department_id

LEFT JOIN users u
ON u.id=d.uploaded_by

WHERE d.status='pending'

ORDER BY d.created_at DESC

");



// ==============================
// APPROVAL HISTORY
// ==============================

$history = fetchAll("

SELECT

d.title,
d.status,
d.reviewed_at,

CONCAT(
u.first_name,
' ',
u.last_name
) AS reviewer

FROM documents d

LEFT JOIN users u
ON u.id=d.reviewed_by

WHERE d.status IN('approved','rejected')

ORDER BY d.reviewed_at DESC

LIMIT 10

");

?>

<link rel="stylesheet" href="assets/css/approvals.css">


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



<!-- SUMMARY CARDS -->

<div class="approval-cards">


    <div class="approval-card pending-card">

        <i class="fa fa-clock"></i>

        <div>

            <h2>

                <?= $pendingCount ?>

            </h2>

            <p>
                Pending Review
            </p>

        </div>

    </div>



    <div class="approval-card approved-card">

        <i class="fa fa-check-circle"></i>

        <div>

            <h2>

                <?= $approvedCount ?>

            </h2>

            <p>
                Approved Documents
            </p>

        </div>

    </div>



    <div class="approval-card rejected-card">

        <i class="fa fa-times-circle"></i>

        <div>

            <h2>

                <?= $rejectedCount ?>

            </h2>

            <p>
                Rejected Documents
            </p>

        </div>

    </div>


</div>





<!-- PENDING APPROVALS -->

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

        <?php foreach($pendingDocuments as $doc){ ?>

        <tr>

            <td>

                <i class="fa

                <?php

                $type = strtolower($doc['file_type']);

                if(str_contains($type,'pdf')){

                    echo "fa-file-pdf pdf-icon";

                }elseif(str_contains($type,'word')){

                    echo "fa-file-word word-icon";

                }else{

                    echo "fa-file";

                }

                ?>

                "></i>

                <?= htmlspecialchars($doc['title']) ?>

            </td>


            <td>

                <?= htmlspecialchars($doc['department_name']) ?>

            </td>


            <td>

                <?= htmlspecialchars($doc['owner_name']) ?>

            </td>


            <td>

                <?= date(
                    "d M Y",
                    strtotime($doc['created_at'])
                ) ?>

            </td>


            <td>

                <span class="approval-status pending">

                    Pending

                </span>

            </td>


            <td>

                <button

                    class="approve-btn"

                    onclick="approveDocument(
                    <?= $doc['id'] ?>
                    )">

                    <i class="fa fa-check"></i>

                    Approve

                </button>


                <button

                    class="reject-btn"

                    onclick="rejectDocument(
                    <?= $doc['id'] ?>
                    )">

                    <i class="fa fa-times"></i>

                    Reject

                </button>

            </td>

        </tr>

        <?php } ?>


        <?php if(empty($pendingDocuments)){ ?>

        <tr>

            <td colspan="6" style="text-align:center;padding:30px;">

                No pending approval requests.

            </td>

        </tr>

        <?php } ?>

        </tbody>

    </table>


</div>






<!-- APPROVAL HISTORY -->

<div class="history-container">


    <h3>

        Approval History

    </h3>


    <?php foreach($history as $item){ ?>


    <div class="history-item">


        <div class="history-icon <?= $item['status'] ?>">


            <?php if($item['status']=="approved"){ ?>

                <i class="fa fa-check"></i>

            <?php }else{ ?>

                <i class="fa fa-times"></i>

            <?php } ?>


        </div>


        <div>


            <h4>

                <?= htmlspecialchars($item['title']) ?>

                <?= ucfirst($item['status']) ?>

            </h4>


            <p>

<?= ucfirst($item['status']) ?>

by

<strong>

<?= !empty($item['reviewer'])

? htmlspecialchars($item['reviewer'])

: "Unknown"

?>
</strong>

on

<?= !empty($item['reviewed_at'])

? date(
"d M Y H:i",
strtotime($item['reviewed_at'])
)

: "Not recorded"

?>
</p>


        </div>


    </div>


    <?php } ?>


    <?php if(empty($history)){ ?>

        <p>

            No approval history available.

        </p>

    <?php } ?>


</div>



<script src="assets/js/approvals.js"></script>


<?php

include "includes/footer.php";

?>