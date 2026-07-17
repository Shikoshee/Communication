<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();


// ======================================
// SUMMARY
// ======================================

$totalDocuments = countRows("
    SELECT id
    FROM documents
");

$totalApproved = countRows("
    SELECT id
    FROM documents
    WHERE status='approved'
");

$totalPending = countRows("
    SELECT id
    FROM documents
    WHERE status='pending'
");

$totalShared = countRows("
    SELECT id
    FROM document_sharing
");


// ======================================
// MONTHLY UPLOADS
// ======================================

$monthlyUploads = fetchAll("

SELECT

MONTH(created_at) AS month,
COUNT(id) AS total

FROM documents

GROUP BY MONTH(created_at)

ORDER BY MONTH(created_at)

");


// ======================================
// APPROVAL STATUS
// ======================================

$approvalStats = fetchAll("

SELECT

status,
COUNT(id) AS total

FROM documents

GROUP BY status

");


// ======================================
// DEPARTMENT PERFORMANCE
// ======================================

$departmentPerformance = fetchAll("

SELECT

d.name,
COUNT(doc.id) AS total

FROM departments d

LEFT JOIN documents doc

ON doc.department_id=d.id

GROUP BY d.id

ORDER BY total DESC

");


// ======================================
// DOCUMENT TYPES
// ======================================

$documentTypes = fetchAll("

SELECT

IFNULL(file_type,'Unknown') AS file_type,
COUNT(id) AS total

FROM documents

GROUP BY file_type

");


// ======================================
// RECENT ACTIVITY
// ======================================

$activities = fetchAll("

SELECT

a.created_at,

CONCAT(
u.first_name,
' ',
u.last_name
) AS user_name,

a.activity,

d.name AS department

FROM activity_logs a

LEFT JOIN users u

ON u.id=a.user_id

LEFT JOIN departments d

ON d.id=a.department_id

ORDER BY a.created_at DESC

LIMIT 10

");


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

$pageTitle = "Reports & Analytics";
$breadcrumb = "Dashboard / Reports";
$buttonText = "Export Report";
$buttonLink = "#";

include "includes/page-header.php";

?>

<link rel="stylesheet" href="assets/css/reports.css">


<!-- ========================= -->
<!-- KPI CARDS -->
<!-- ========================= -->

<div class="report-summary">

    <div class="summary-card blue">

        <i class="fa-solid fa-file-lines"></i>

        <div>

            <h2><?= $totalDocuments ?></h2>

            <p>Total Documents</p>

        </div>

    </div>

    <div class="summary-card green">

        <i class="fa-solid fa-circle-check"></i>

        <div>

            <h2><?= $totalApproved ?></h2>

            <p>Approved</p>

        </div>

    </div>

    <div class="summary-card orange">

        <i class="fa-solid fa-clock"></i>

        <div>

            <h2><?= $totalPending ?></h2>

            <p>Pending</p>

        </div>

    </div>

    <div class="summary-card red">

        <i class="fa-solid fa-share-nodes"></i>

        <div>

            <h2><?= $totalShared ?></h2>

            <p>Shared Files</p>

        </div>

    </div>

</div>



<!-- ========================= -->
<!-- FILTER -->
<!-- ========================= -->

<div class="report-toolbar">

    <input
        type="date"
        id="startDate">

    <input
        type="date"
        id="endDate">

    <button
        class="filter-btn"
        onclick="applyFilter()">

        <i class="fa fa-filter"></i>

        Apply Filter

    </button>

    <button
        class="excel-btn"
        onclick="exportExcel()">

        <i class="fa fa-file-excel"></i>

        Excel

    </button>

    <button
        class="pdf-btn"
        onclick="exportPDF()">

        <i class="fa fa-file-pdf"></i>

        PDF

    </button>

</div>



<!-- ========================= -->
<!-- CHARTS -->
<!-- ========================= -->

<div class="charts-grid">

    <div class="chart-card">

        <h3>Monthly Uploads</h3>

        <canvas id="uploadsChart"></canvas>

    </div>

    <div class="chart-card">

        <h3>Approval Status</h3>

        <canvas id="approvalChart"></canvas>

    </div>

</div>



<div class="charts-grid">

    <div class="chart-card">

        <h3>Department Performance</h3>

        <canvas id="departmentPerformance"></canvas>

    </div>

    <div class="chart-card">

        <h3>Document Types</h3>

        <canvas id="documentTypes"></canvas>

    </div>

</div>



<!-- ========================= -->
<!-- RECENT ACTIVITY -->
<!-- ========================= -->

<div class="activity-card">

    <h3>Recent Activity</h3>

    <table>

        <thead>

            <tr>

                <th>Date</th>

                <th>User</th>

                <th>Action</th>

                <th>Department</th>

            </tr>

        </thead>

        <tbody>

        <?php if(count($activities) > 0){ ?>

            <?php foreach($activities as $activity){ ?>

                <tr>

                    <td>

                        <?= date(
                            "d M Y H:i",
                            strtotime($activity['created_at'])
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($activity['user_name']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($activity['activity']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $activity['department'] ?? '-'
                        ) ?>

                    </td>

                </tr>

            <?php } ?>

        <?php } else { ?>

            <tr>

                <td colspan="4" style="text-align:center">

                    No activity found.

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

</div>



<!-- ========================= -->
<!-- PASS DATA TO JAVASCRIPT -->
<!-- ========================= -->

<script>

const uploadsData =
<?= json_encode($monthlyUploads) ?>;

const approvalData =
<?= json_encode($approvalStats) ?>;

const departmentData =
<?= json_encode($departmentPerformance) ?>;

const documentTypeData =
<?= json_encode($documentTypes) ?>;

</script>


<script src="assets/js/reports.js"></script>

<?php include "includes/footer.php"; ?>