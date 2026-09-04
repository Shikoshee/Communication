<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();


/*
 * ==========================================================
 * CURRENT USER / ROLE
 * ==========================================================
 */

$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim(
        (string)($user['role'] ?? 'user')
    )
);

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
 * ==========================================================
 * ACCESS
 * ==========================================================
 */

if (!$isAdmin && !$isManager) {

    die("Access Denied.");

}


/*
 * ==========================================================
 * GET MANAGER DEPARTMENT
 * ==========================================================
 */

$departmentId = (int)(
    $user['department_id'] ?? 0
);


/*
 * Some sessions may not contain department_id.
 * Get it directly from the database.
 */

if ($isManager && $departmentId <= 0) {

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
 * MANAGER MUST HAVE DEPARTMENT
 * ==========================================================
 */

if ($isManager && $departmentId <= 0) {

    die(
        "Your account is not assigned to a department."
    );

}


/*
 * ==========================================================
 * PAGE
 * ==========================================================
 */

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

$pageTitle = "Users Management";

$breadcrumb = $isAdmin
    ? "Dashboard / Users"
    : "Dashboard / Department Users";

$buttonText = "Add User";

$buttonLink = "javascript:addUser();";

include "includes/page-header.php";


/*
 * ==========================================================
 * SUMMARY
 * ==========================================================
 */

if ($isAdmin) {

    /*
     * ADMIN:
     * Organization-wide statistics.
     */

    $totalUsers = countRows("
        SELECT id
        FROM users
    ");

    $activeUsers = countRows("
        SELECT id
        FROM users
        WHERE status='active'
    ");

    $inactiveUsers = countRows("
        SELECT id
        FROM users
        WHERE status='inactive'
    ");

    $lockedUsers = countRows("
        SELECT id
        FROM users
        WHERE status='locked'
    ");

} else {

    /*
     * MANAGER:
     * Statistics for manager's department only.
     */

    $totalUsers = countRows(
        "
        SELECT id
        FROM users
        WHERE department_id=?
        ",
        [
            $departmentId
        ]
    );

    $activeUsers = countRows(
        "
        SELECT id
        FROM users
        WHERE department_id=?
        AND status='active'
        ",
        [
            $departmentId
        ]
    );

    $inactiveUsers = countRows(
        "
        SELECT id
        FROM users
        WHERE department_id=?
        AND status='inactive'
        ",
        [
            $departmentId
        ]
    );

    $lockedUsers = countRows(
        "
        SELECT id
        FROM users
        WHERE department_id=?
        AND status='locked'
        ",
        [
            $departmentId
        ]
    );

}


/*
 * ==========================================================
 * DEPARTMENTS
 * ==========================================================
 *
 * ADMIN:
 *     All departments.
 *
 * MANAGER:
 *     Own department only.
 */

if ($isAdmin) {

    $departments = fetchAll("
        SELECT
            id,
            name
        FROM departments
        ORDER BY name
    ");

} else {

    $departments = fetchAll(
        "
        SELECT
            id,
            name
        FROM departments
        WHERE id=?
        ORDER BY name
        ",
        [
            $departmentId
        ]
    );

}


/*
 * ==========================================================
 * USERS
 * ==========================================================
 *
 * ADMIN:
 *     All users.
 *
 * MANAGER:
 *     Users in manager's department only.
 */

if ($isAdmin) {

    $users = fetchAll("

        SELECT

            u.*,

            d.name AS department_name

        FROM users u

        LEFT JOIN departments d
            ON d.id = u.department_id

        ORDER BY
            u.first_name,
            u.last_name

    ");

} else {

    $users = fetchAll(
        "

        SELECT

            u.*,

            d.name AS department_name

        FROM users u

        LEFT JOIN departments d
            ON d.id = u.department_id

        WHERE u.department_id=?

        ORDER BY
            u.first_name,
            u.last_name

        ",
        [
            $departmentId
        ]
    );

}


/*
 * ==========================================================
 * CHART
 * ==========================================================
 *
 * ADMIN ONLY
 *
 * Managers do not need chart data because they only
 * manage users within their own department.
 */

$chartLabels = [];
$chartValues = [];

if ($isAdmin) {

    $chartData = fetchAll("

        SELECT

            d.name,

            COUNT(u.id) AS total_users

        FROM departments d

        LEFT JOIN users u
            ON u.department_id = d.id

        GROUP BY
            d.id,
            d.name

        ORDER BY
            d.name

    ");

    foreach ($chartData as $row) {

        $chartLabels[] = $row['name'];

        $chartValues[] = (int)$row['total_users'];

    }

}

/*
 * ==========================================================
 * MANAGER DEPARTMENT NAME
 * ==========================================================
 */

$managerDepartmentName = '';

if ($isManager) {

    $managerDepartment = fetchRow(
        "
        SELECT name
        FROM departments
        WHERE id=?
        LIMIT 1
        ",
        [
            $departmentId
        ]
    );

    $managerDepartmentName =
        $managerDepartment['name']
        ?? 'Your Department';

}

?>

<link rel="stylesheet" href="assets/css/users.css">
<link rel="stylesheet" href="assets/css/users.css?v=2">


<!-- ==========================================================
     SUMMARY
     ========================================================== -->

<div class="user-summary">


    <!-- TOTAL USERS -->

    <div class="summary-card blue">

        <i class="fa-solid fa-users"></i>

        <div>

            <h2>
                <?= (int)$totalUsers ?>
            </h2>

            <p>
                <?= $isAdmin
                    ? 'Total Users'
                    : 'Department Users'
                ?>
            </p>

        </div>

    </div>


    <!-- ACTIVE USERS -->

    <div class="summary-card green">

        <i class="fa-solid fa-user-check"></i>

        <div>

            <h2>
                <?= (int)$activeUsers ?>
            </h2>

            <p>
                Active Users
            </p>

        </div>

    </div>


    <!-- INACTIVE USERS -->

    <div class="summary-card orange">

        <i class="fa-solid fa-user-xmark"></i>

        <div>

            <h2>
                <?= (int)$inactiveUsers ?>
            </h2>

            <p>
                Inactive Users
            </p>

        </div>

    </div>


    <!-- LOCKED USERS -->

    <div class="summary-card red">

        <i class="fa-solid fa-user-lock"></i>

        <div>

            <h2>
                <?= (int)$lockedUsers ?>
            </h2>

            <p>
                Locked Accounts
            </p>

        </div>

    </div>

</div>


<!-- ==========================================================
     MANAGER DEPARTMENT NOTICE
     ========================================================== -->

<?php if ($isManager) { ?>

    <div
        class="permission-scope"
        style="margin-bottom:20px;"
    >

        <i class="fa fa-building"></i>

        Viewing users in:

        <strong>
            <?= htmlspecialchars(
                $managerDepartmentName
            ) ?>
        </strong>

    </div>

<?php } ?>


<!-- ==========================================================
     TOOLBAR
     ========================================================== -->

<div class="user-toolbar">

    <input
        type="text"
        id="userSearch"
        placeholder="Search users..."
    >


    <select id="departmentFilter">

        <?php if ($isAdmin) { ?>

            <option value="">
                All Departments
            </option>

        <?php } ?>


        <?php foreach ($departments as $department) { ?>

            <option
                value="<?= (int)$department['id'] ?>"
                <?= (
                    $isManager
                    && (int)$department['id'] === $departmentId
                )
                    ? 'selected'
                    : ''
                ?>
            >

                <?= htmlspecialchars(
                    $department['name']
                ) ?>

            </option>

        <?php } ?>

    </select>

</div>


<!-- ==========================================================
     USERS TABLE
     ========================================================== -->

<div class="user-table">

    <h3>

        <?= $isAdmin
            ? 'System Users'
            : 'Department Users'
        ?>

    </h3>


    <table>

        <thead>

            <tr>

                <th>Users</th>

                <th>Name</th>

                <th>Email</th>

                <th>Department</th>

                <th>Role</th>

                <th>Status</th>

                <th>Last Login</th>

                <th>Actions</th>

            </tr>

        </thead>


        <tbody id="usersTable">

        <?php foreach ($users as $u) { ?>

            <?php

            $fullName =
                trim(
                    ($u['first_name'] ?? '')
                    . ' '
                    . ($u['last_name'] ?? '')
                );

            $searchName = strtolower(
                $fullName
            );


            ?>

            <tr

                data-name="<?= htmlspecialchars(
                    $searchName,
                    ENT_QUOTES
                ) ?>"

                data-department="<?= (int)(
                    $u['department_id'] ?? 0
                ) ?>"

            >


                <!-- PHOTO -->

                <td>

    <div class="user-avatar">
        <i class="fa-solid fa-user"></i>
    </div>

</td>


                <!-- NAME -->

                <td>

                    <?= htmlspecialchars(
                        $fullName
                    ) ?>

                </td>


                <!-- EMAIL -->

                <td>

                    <?= htmlspecialchars(
                        $u['email'] ?? '-'
                    ) ?>

                </td>


                <!-- DEPARTMENT -->

                <td>

                    <?= htmlspecialchars(
                        $u['department_name'] ?? '-'
                    ) ?>

                </td>


                <!-- ROLE -->

                <td>

                    <span
                        class="role <?= htmlspecialchars(
                            strtolower(
                                (string)($u['role'] ?? '')
                            ),
                            ENT_QUOTES
                        ) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                (string)($u['role'] ?? '')
                            )
                        ) ?>

                    </span>

                </td>


                <!-- STATUS -->

                <td>

                    <span
                        class="status <?= htmlspecialchars(
                            strtolower(
                                (string)($u['status'] ?? '')
                            ),
                            ENT_QUOTES
                        ) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                (string)($u['status'] ?? '')
                            )
                        ) ?>

                    </span>

                </td>


                <!-- LAST LOGIN -->

                <td>

                    <?php if (!empty($u['last_login'])) { ?>

                        <?= date(
                            "d M Y H:i",
                            strtotime(
                                $u['last_login']
                            )
                        ) ?>

                    <?php } else { ?>

                        -

                    <?php } ?>

                </td>


                <!-- ACTIONS -->

                <td>

                    <button
                        class="view-btn"
                        onclick="viewUser(
                            <?= (int)$u['id'] ?>
                        )"
                    >

                        <i class="fa fa-eye"></i>

                    </button>


                    <button
                        class="edit-btn"
                        onclick="editUser(
                            <?= (int)$u['id'] ?>
                        )"
                    >

                        <i class="fa fa-edit"></i>

                    </button>


                    <button
                        class="reset-btn"
                        onclick="resetPassword(
                            <?= (int)$u['id'] ?>
                        )"
                    >

                        <i class="fa fa-key"></i>

                    </button>


                    <button
                        class="lock-btn"
                        onclick="lockUser(
                            <?= (int)$u['id'] ?>
                        )"
                    >

                        <i class="fa fa-lock"></i>

                    </button>


                    <button
                        class="delete-btn"
                        onclick="deleteUser(
                            <?= (int)$u['id'] ?>,
                            '<?= htmlspecialchars(
                                $fullName,
                                ENT_QUOTES
                            ) ?>'
                        )"
                    >

                        <i class="fa fa-trash"></i>

                    </button>

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

</div>


<!-- ==========================================================
     CHART
     ========================================================== -->
<?php if ($isAdmin) { ?>

    <!-- ==========================================================
         ADMIN ONLY — USERS BY DEPARTMENT CHART
         ========================================================== -->

    <div class="user-chart">

        <h3>
            Users by Department
        </h3>

        <canvas id="userChart"></canvas>

    </div>

<?php } ?>


<script>

const userChartLabels =
    <?= json_encode($chartLabels) ?>;

const userChartValues =
    <?= json_encode($chartValues) ?>;

const currentUserRole =
    <?= json_encode($userRole) ?>;

const currentDepartmentId =
    <?= json_encode($departmentId) ?>;

</script>


<script src="assets/js/users.js"></script>


<?php include "includes/footer.php"; ?>

