<?php

require_once "includes/config.php";
require_once "includes/auth.php";
require_once "includes/Dashboard.php";

Auth::protect();

$user = Auth::getCurrentUser();

$userId = (int)(
    $user['id'] ?? 0
);


$userRole = strtolower(
    trim(
        (string)(
            $user['role'] ?? 'user'
        )
    )
);


/*
 * ==========================================================
 * GET DEPARTMENT ID
 * ==========================================================
 *
 * First try the session/current-user array.
 *
 * If it is missing, retrieve it directly from the users table.
 */

$departmentId = (int)(
    $user['department_id'] ?? 0
);


if($departmentId <= 0){

    $departmentRow = fetchRow(

        "SELECT department_id

         FROM users

         WHERE id=?

         LIMIT 1",

        [
            $userId
        ]

    );


    $departmentId = (int)(
        $departmentRow['department_id'] ?? 0
    );

}


/*
 * ==========================================================
 * VALIDATE USER
 * ==========================================================
 */

if($userId <= 0){

    die("Invalid user session.");

}


/*
 * ==========================================================
 * ROLE DETECTION
 * ==========================================================
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
    || str_contains($userRole, 'manager')
);

$isManagerOrAdmin =
    $isAdmin ||
    $isManager;


/*
 * ==========================================================
 * DASHBOARD STATISTICS
 * ==========================================================
 */

$stats = Dashboard::getStatistics(
    $userId,
    $userRole,
    $departmentId
);


/*
 * ==========================================================
 * RECENT ACTIVITY
 * ==========================================================
 */

$activity = Dashboard::getRecentActivity(
    $userId,
    10,
    $userRole,
    $departmentId
);


/*
 * ==========================================================
 * PENDING APPROVALS
 * ==========================================================
 */

$approvals = Dashboard::getPendingApprovals(
    $userId,
    $userRole,
    $departmentId,
    5
);


/*
 * ==========================================================
 * DEFAULT DATA
 * ==========================================================
 */

$departments  = [];
$statusChart  = [];
$monthlyUploads = [];
$documents    = [];
$uploaders    = [];


/*
 * ==========================================================
 * MANAGER / ADMIN DATA
 * ==========================================================
 */

if($isManagerOrAdmin){

    /*
     * Department chart.
     */

    $departments = Dashboard::getDepartmentActivity(
        $userRole,
        $departmentId
    );


    /*
     * Status chart.
     */

    $statusChart = Dashboard::getDocumentStatusChart(
        $userRole,
        $departmentId
    );


    /*
     * Monthly uploads.
     */

    $monthlyUploads = Dashboard::getMonthlyUploads(
        $userRole,
        $departmentId
    );


    /*
     * Recent documents.
     *
     * IMPORTANT:
     * Correct argument order:
     *
     * userId,
     * role,
     * departmentId,
     * limit
     */

    $documents = Dashboard::getRecentDocuments(
        $userId,
        $userRole,
        $departmentId,
        10
    );


    /*
     * Top uploaders.
     */

    $uploaders = Dashboard::getTopUploaders(
        $userRole,
        $departmentId
    );

}


/*
 * ==========================================================
 * NOTIFICATIONS
 * ==========================================================
 */

$notifications = Dashboard::getUnreadNotifications(
    $userId
);


/*
 * ==========================================================
 * STORAGE
 * ==========================================================
 */

$storage = Dashboard::getStorageUsage(
    $userId
);


/*
 * ==========================================================
 * PAGE INCLUDES
 * ==========================================================
 */

include "includes/header.php";

include "includes/sidebar.php";

include "includes/navbar.php";

?>


<!-- ==========================================================
     WELCOME
========================================================== -->

<div class="welcome">

    <h1>
        Dashboard
    </h1>

    <p>

        Welcome back,

        <strong>

            <?= htmlspecialchars(
                trim(
                    ($user['first_name'] ?? '') .
                    ' ' .
                    ($user['last_name'] ?? '')
                )
            ) ?>

        </strong>

    </p>

</div>


<!-- ==========================================================
     STATISTICS CARDS
========================================================== -->

<div class="cards">


<?php if($isManagerOrAdmin){ ?>


    <!-- TOTAL DOCUMENTS -->

    <div class="card blue">

        <i class="fas fa-folder"></i>

        <h2>
            <?= (int)$stats['total_documents'] ?>
        </h2>

        <p>

            <?= $isAdmin
                ? 'Total Documents'
                : 'Department Documents'
            ?>

        </p>

    </div>


    <!-- APPROVED -->

    <div class="card green">

        <i class="fas fa-check-circle"></i>

        <h2>
            <?= (int)$stats['approved_documents'] ?>
        </h2>

        <p>
            Approved Documents
        </p>

    </div>


    <!-- PENDING -->

    <div class="card orange">

        <i class="fas fa-clock"></i>

        <h2>
            <?= (int)$stats['pending_documents'] ?>
        </h2>

        <p>
            Pending Approval
        </p>

    </div>


    <!-- REJECTED -->

    <div class="card red">

        <i class="fas fa-times-circle"></i>

        <h2>
            <?= (int)$stats['rejected_documents'] ?>
        </h2>

        <p>
            Rejected Documents
        </p>

    </div>


    <!-- SHARED -->

    <div class="card purple">

        <i class="fas fa-share-alt"></i>

        <h2>
            <?= (int)$stats['shared_documents'] ?>
        </h2>

        <p>
            <?= $isAdmin
                ? 'Shared Documents'
                : 'Department Shared'
            ?>
        </p>

    </div>


    <!-- USERS -->

    <div class="card cyan">

        <i class="fas fa-users"></i>

        <h2>
            <?= (int)$stats['users'] ?>
        </h2>

        <p>

            <?= $isAdmin
                ? 'Active Users'
                : 'Department Users'
            ?>

        </p>

    </div>


    <!-- DEPARTMENTS -->

    <div class="card dark">

        <i class="fas fa-building"></i>

        <h2>
            <?= (int)$stats['departments'] ?>
        </h2>

        <p>

            <?= $isAdmin
                ? 'Departments'
                : 'My Department'
            ?>

        </p>

    </div>


<?php } else { ?>


    <!-- MY DOCUMENTS -->

    <div class="card blue">

        <i class="fas fa-folder"></i>

        <h2>
            <?= (int)$stats['total_documents'] ?>
        </h2>

        <p>
            My Documents
        </p>

    </div>


    <!-- APPROVED -->

    <div class="card green">

        <i class="fas fa-check-circle"></i>

        <h2>
            <?= (int)$stats['approved_documents'] ?>
        </h2>

        <p>
            Approved by Me
        </p>

    </div>


    <!-- PENDING -->

    <div class="card orange">

        <i class="fas fa-clock"></i>

        <h2>
            <?= (int)$stats['pending_documents'] ?>
        </h2>

        <p>
            My Pending Documents
        </p>

    </div>


    <!-- REJECTED -->

    <div class="card red">

        <i class="fas fa-times-circle"></i>

        <h2>
            <?= (int)$stats['rejected_documents'] ?>
        </h2>

        <p>
            Rejected by Me
        </p>

    </div>


    <!-- SHARED BY ME -->

    <div class="card purple">

        <i class="fas fa-share-alt"></i>

        <h2>
            <?= (int)$stats['shared_documents'] ?>
        </h2>

        <p>
            Shared by Me
        </p>

    </div>


    <!-- SHARED WITH ME -->

    <div class="card cyan">

        <i class="fas fa-inbox"></i>

        <h2>
            <?= (int)$stats['received_documents'] ?>
        </h2>

        <p>
            Shared With Me
        </p>

    </div>


<?php } ?>


    <!-- NOTIFICATIONS -->
<!-- NOTIFICATIONS -->

<a href="/Communication/notifications.php" class="card yellow">

    <i class="fas fa-bell"></i>

    <h2>
        <?= (int)$stats['notifications'] ?>
    </h2>

    <p>
        Unread Notifications
    </p>

</a>


    <!-- STORAGE -->

    <div class="card dark">

        <i class="fas fa-database"></i>

        <h2>
            <?= htmlspecialchars($storage) ?>
        </h2>

        <p>
            My Storage
        </p>

    </div>


</div>


<?php if($isManagerOrAdmin){ ?>


<!-- ==========================================================
     MANAGER / ADMIN CHARTS
========================================================== -->

<div class="charts">


    <!-- DEPARTMENT -->

    <div class="chart-card">

        <h3>

            <?= $isAdmin
                ? 'Documents By Department'
                : 'Documents In My Department'
            ?>

        </h3>

        <canvas id="departmentChart"></canvas>

    </div>


    <!-- STATUS -->

    <div class="chart-card">

        <h3>
            Document Status
        </h3>

        <canvas id="statusChart"></canvas>

    </div>


    <!-- MONTHLY -->

    <div class="chart-card">

        <h3>

            <?= $isAdmin
                ? 'Monthly Uploads'
                : 'Monthly Uploads - My Department'
            ?>

        </h3>

        <canvas id="uploadChart"></canvas>

    </div>


</div>


<!-- ==========================================================
     RECENT DOCUMENTS
========================================================== -->

<div class="table-card">

    <h3>

        <?= $isAdmin
            ? 'Recent Documents'
            : 'Recent Documents - My Department'
        ?>

    </h3>


    <table>

        <thead>

            <tr>

                <th>Document</th>

                <th>Department</th>

                <th>Uploaded By</th>

                <th>Status</th>

                <th>Date</th>

            </tr>

        </thead>


        <tbody>


        <?php if(empty($documents)){ ?>

            <tr>

                <td
                    colspan="5"
                    style="text-align:center;padding:30px;"
                >

                    No recent documents found.

                </td>

            </tr>

        <?php } ?>


        <?php foreach($documents as $doc){ ?>

    <?php

    $docStatus = strtolower(
        trim(
            (string)(
                $doc['status'] ?? ''
            )
        )
    );

    /*
     * Documents with an empty/NULL status are considered pending.
     */
    if ($docStatus === '') {
        $docStatus = 'pending';
    }

    ?>

    <tr>

        <td>
            <?= htmlspecialchars(
                $doc['title'] ?? 'Untitled'
            ) ?>
        </td>

        <td>
            <?= htmlspecialchars(
                $doc['department'] ?? '-'
            ) ?>
        </td>

        <td>
            <?= htmlspecialchars(
                trim(
                    (string)(
                        $doc['uploaded_by'] ?? '-'
                    )
                )
            ) ?>
        </td>

        <td>

            <span
                class="badge <?= htmlspecialchars(
                    $docStatus,
                    ENT_QUOTES
                ) ?>"
            >

                <?= htmlspecialchars(
                    ucfirst($docStatus)
                ) ?>

            </span>

        </td>

        <td>

            <?php

            $createdAt =
                $doc['created_at'] ?? null;

            echo $createdAt
                ? date(
                    "d M Y",
                    strtotime($createdAt)
                )
                : '-';

            ?>

        </td>

    </tr>

<?php } ?>


        </tbody>

    </table>

</div>


<!-- ==========================================================
     BOTTOM PANELS
========================================================== -->

<div class="dashboard-grid">


    <!-- RECENT ACTIVITY -->

    <div class="table-card">

        <h3>
            Recent Activity
        </h3>


        <?php if(empty($activity)){ ?>

            <p>
                No recent activity.
            </p>

        <?php } ?>


        <?php foreach($activity as $item){ ?>

            <div class="activity-item">

                <strong>

                    <?= htmlspecialchars(
                        $item['user_name'] ?? 'User'
                    ) ?>

                </strong>


                <p>

                    <?= htmlspecialchars(
                        $item['action'] ?? ''
                    ) ?>

                    <br>

                    <small>

                        <?= !empty($item['created_at'])
                            ? date(
                                "d M Y H:i",
                                strtotime(
                                    $item['created_at']
                                )
                            )
                            : '-'
                        ?>

                    </small>

                </p>

            </div>

        <?php } ?>


    </div>


    <!-- PENDING APPROVALS -->

    <div class="table-card">

        <h3>

            <?= $isAdmin
                ? 'Pending Approvals'
                : 'Pending Approvals - My Department'
            ?>

        </h3>


        <?php if(empty($approvals)){ ?>

            <p>
                No pending approvals.
            </p>

        <?php } ?>


        <?php foreach($approvals as $approval){ ?>

            <div class="approval-item">

                <strong>

                    <?= htmlspecialchars(
                        $approval['title'] ?? 'Untitled'
                    ) ?>

                </strong>


                <p>

                    <?= htmlspecialchars(
                        $approval['department'] ?? '-'
                    ) ?>

                    <br>

                    <small>

                        Uploaded by:

                        <?= htmlspecialchars(
                            $approval['uploaded_by'] ?? '-'
                        ) ?>

                    </small>

                </p>

            </div>

        <?php } ?>


    </div>


    <!-- TOP UPLOADERS -->

    <div class="table-card">

        <h3>

            <?= $isAdmin
                ? 'Top Uploaders'
                : 'Top Uploaders - My Department'
            ?>

        </h3>


        <?php if(empty($uploaders)){ ?>

            <p>
                No upload activity found.
            </p>

        <?php } ?>


        <?php foreach($uploaders as $person){ ?>

            <p>

                <i class="fas fa-user"></i>

                <?= htmlspecialchars(
                    $person['name'] ?? 'User'
                ) ?>

                <strong>

                    (
                    <?= (int)(
                        $person['total'] ?? 0
                    ) ?>
                    )

                </strong>

            </p>

        <?php } ?>


    </div>


</div>


<?php } else { ?>


<!-- ==========================================================
     REGULAR USER PANELS
========================================================== -->

<div class="dashboard-grid">


    <!-- RECENT ACTIVITY -->

    <div class="table-card">

        <h3>
            Recent Activity
        </h3>


        <?php if(empty($activity)){ ?>

            <p>
                No recent activity.
            </p>

        <?php } ?>


        <?php foreach($activity as $item){ ?>

            <div class="activity-item">

                <strong>

                    <?= htmlspecialchars(
                        $item['user_name'] ?? 'You'
                    ) ?>

                </strong>


                <p>

                    <?= htmlspecialchars(
                        $item['action'] ?? ''
                    ) ?>

                    <br>

                    <small>

                        <?= !empty($item['created_at'])
                            ? date(
                                "d M Y H:i",
                                strtotime(
                                    $item['created_at']
                                )
                            )
                            : '-'
                        ?>

                    </small>

                </p>

            </div>

        <?php } ?>


    </div>


    <!-- MY PENDING -->

    <div class="table-card">

        <h3>
            My Pending Documents
        </h3>


        <?php if(empty($approvals)){ ?>

            <p>
                You have no pending documents.
            </p>

        <?php } ?>


        <?php foreach($approvals as $approval){ ?>

            <div class="approval-item">

                <strong>

                    <?= htmlspecialchars(
                        $approval['title'] ?? 'Untitled'
                    ) ?>

                </strong>


                <p>

                    <?= htmlspecialchars(
                        $approval['department'] ?? '-'
                    ) ?>

                    <br>

                    <small>

                        Status:

                        <?= htmlspecialchars(
                            ucfirst(
                                $approval['status'] ?? 'pending'
                            )
                        ) ?>

                    </small>

                </p>

            </div>

        <?php } ?>


    </div>


</div>


<?php } ?>


<?php if($isManagerOrAdmin){ ?>


<!-- ==========================================================
     CHART.JS
========================================================== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>

window.dashboardChartData = {

    departmentLabels:
        <?= json_encode(
            array_values(
                array_column(
                    $departments,
                    'name'
                )
            ),
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>,

    departmentValues:
        <?= json_encode(
            array_map(
                'intval',
                array_column(
                    $departments,
                    'total'
                )
            )
        ) ?>,

    statusLabels:
        <?= json_encode(
            array_values(
                array_column(
                    $statusChart,
                    'status'
                )
            ),
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>,

    statusValues:
        <?= json_encode(
            array_map(
                'intval',
                array_column(
                    $statusChart,
                    'total'
                )
            )
        ) ?>,

    uploadLabels:
        <?= json_encode(
            array_values(
                array_column(
                    $monthlyUploads,
                    'month'
                )
            ),
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>,

    uploadValues:
        <?= json_encode(
            array_map(
                'intval',
                array_column(
                    $monthlyUploads,
                    'total'
                )
            )
        ) ?>

};

</script>


<script src="assets/js/dashboard.js"></script>


<?php } ?>


<?php

include "includes/footer.php";

?>