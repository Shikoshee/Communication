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
$canShare = Permission::canShare();


/*
 * ==========================================================
 * ROLE CHECK
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


if (!$isAdmin && !$isManager) {

    die("Access Denied.");

}


/*
 * ==========================================================
 * GET CURRENT USER DEPARTMENT
 * ==========================================================
 */

$departmentId = (int)(
    $user['department_id'] ?? 0
);


if ($departmentId <= 0) {

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

    die("Your account is not assigned to a department.");

}


/*
 * ==========================================================
 * SUMMARY
 * ==========================================================
 */

if ($isAdmin) {

    $totalUsers = countRows("

        SELECT id
        FROM users
        WHERE status='active'

    ");


    $totalAdmins = countRows("

        SELECT id
        FROM users
        WHERE role IN ('admin', 'administrator')

    ");


    $totalDepartments = countRows("

        SELECT id
        FROM departments

    ");


    $usersWithPermissions = countRows("

        SELECT DISTINCT user_id
        FROM permissions

    ");

} else {

    /*
     * ------------------------------------------------------
     * MANAGER SUMMARY
     * ------------------------------------------------------
     */

    $totalUsers = countRows("

        SELECT id
        FROM users
        WHERE department_id=?
        AND status='active'

    ", [

        $departmentId

    ]);


    $totalAdmins = countRows("

        SELECT id
        FROM users
        WHERE department_id=?
        AND role IN ('admin', 'administrator')

    ", [

        $departmentId

    ]);


    $usersWithPermissions = countRows("

        SELECT DISTINCT p.user_id

        FROM permissions p

        INNER JOIN users u
            ON u.id = p.user_id

        WHERE u.department_id=?

    ", [

        $departmentId

    ]);

}


/*
 * ==========================================================
 * LOAD DOCUMENTS
 * ==========================================================
 *
 * Admin:
 *     All documents.
 *
 * Manager:
 *     Documents belonging to their department.
 */

if ($isAdmin) {

    $documents = fetchAll("

        SELECT
            id,
            title

        FROM documents

        ORDER BY title

    ");

} else {

    $documents = fetchAll("

        SELECT
            id,
            title

        FROM documents

        WHERE department_id=?

        ORDER BY title

    ", [

        $departmentId

    ]);

}


/*
 * ==========================================================
 * LOAD USERS FOR DOCUMENT SHARING
 * ==========================================================
 *
 * Admin:
 *     Can share with any active user.
 *
 * Manager:
 *     Can share with active users in their department.
 */

if ($isAdmin) {

    $shareUsers = fetchAll("

        SELECT

            id,

            first_name,

            last_name,

            email,

            department_id

        FROM users

        WHERE status='active'

        ORDER BY first_name, last_name

    ");

} else {

    $shareUsers = fetchAll("

        SELECT

            id,

            first_name,

            last_name,

            email,

            department_id

        FROM users

        WHERE status='active'

        AND department_id=?

        ORDER BY first_name, last_name

    ", [

        $departmentId

    ]);

}


/*
 * ==========================================================
 * LOAD DEPARTMENTS
 * ==========================================================
 *
 * Kept for the page scope/display.
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

    $departments = fetchAll("

        SELECT
            id,
            name

        FROM departments

        WHERE id=?

        ORDER BY name

    ", [

        $departmentId

    ]);

}


/*
 * ==========================================================
 * MANAGER DEPARTMENT NAME
 * ==========================================================
 */

$managerDepartment = null;

if ($isManager) {

    $managerDepartment = fetchRow(

        "SELECT name
         FROM departments
         WHERE id=?
         LIMIT 1",

        [
            $departmentId
        ]

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

?>

<link rel="stylesheet" href="assets/css/permissions.css">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">

    <div>

        <h1>
            Permissions Management
        </h1>

        <p>

            <?php if ($isAdmin) { ?>

                Control document permissions across the organization.

            <?php } else { ?>

                Manage document permissions for members of your department.

            <?php } ?>

        </p>

    </div>

</div>


<!-- ==========================================================
     SUMMARY
========================================================== -->

<div class="permission-cards">


    <!-- TOTAL USERS -->

    <div class="permission-card blue">

        <i class="fa fa-users"></i>

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



    <!-- ADMINISTRATORS -->

    <div class="permission-card green">

        <i class="fa fa-user-shield"></i>

        <div>

            <h2>
                <?= (int)$totalAdmins ?>
            </h2>

            <p>

                <?= $isAdmin
                    ? 'Administrators'
                    : 'Department Administrators'
                ?>

            </p>

        </div>

    </div>



    <!-- USERS WITH PERMISSIONS -->

    <div class="permission-card permission-purple">

        <i class="fa fa-user-check"></i>

        <div>

            <h2>
                <?= (int)$usersWithPermissions ?>
            </h2>

            <p>

                <?= $isAdmin
                    ? 'Users With Permissions'
                    : 'Department Users With Permissions'
                ?>

            </p>

        </div>

    </div>



    <!-- DEPARTMENTS -->

    <?php if ($isAdmin) { ?>

        <div class="permission-card orange">

            <i class="fa fa-building"></i>

            <div>

                <h2>
                    <?= (int)$totalDepartments ?>
                </h2>

                <p>
                    Departments
                </p>

            </div>

        </div>

    <?php } ?>


</div>



<!-- ==========================================================
     USER PERMISSIONS
========================================================== -->

<div class="permission-container">

    <div class="table-header">

        <div>

            <h3>

                <?= $isAdmin
                    ? 'User Access Permissions'
                    : 'Department Member Permissions'
                ?>

            </h3>


            <?php if ($isManager) { ?>

                <p class="permission-scope">

                    <i class="fa fa-building"></i>

                    Managing permissions for:

                    <strong>

                        <?= htmlspecialchars(
                            $managerDepartment['name']
                            ?? 'Your Department'
                        ) ?>

                    </strong>

                </p>

            <?php } ?>

        </div>


        <button
            type="button"
            class="add-user-btn"
            onclick="loadPermissions()">

            <i class="fa fa-refresh"></i>

            Refresh Permissions

        </button>

    </div>


    <table>

        <thead>

            <tr>

                <th>
                    User
                </th>

                <th>
                    Department
                </th>

                <th>
                    View
                </th>

                <th>
                    Edit
                </th>

                <th>
                    Approve
                </th>

                <th>
                    Delete
                </th>

                <th>
                    Share
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody id="permissionsTable">

        </tbody>

    </table>

</div>


<!-- ==========================================================
     DOCUMENT SHARING
========================================================== -->

<?php if ($canShare) { ?>

<div class="share-container">

    <h3>
        Share Document Permissions
    </h3>


    <div class="share-box">


        <!-- DOCUMENT -->

        <label for="documentSelect">

            Select Document

        </label>


        <select id="documentSelect">

            <option value="">

                Select Document

            </option>


            <?php foreach ($documents as $doc) { ?>

                <option
                    value="<?= (int)$doc['id'] ?>"
                >

                    <?= htmlspecialchars(
                        $doc['title']
                    ) ?>

                </option>

            <?php } ?>

        </select>



        <!-- USER -->

        <label for="userSelect">

            Allow Access To

        </label>


        <select
            id="userSelect"
            multiple
        >

            <?php foreach ($shareUsers as $shareUser) { ?>

                <?php

                $shareUserName = trim(

                    ($shareUser['first_name'] ?? '')
                    . ' '
                    .
                    ($shareUser['last_name'] ?? '')

                );

                ?>

                <option
                    value="<?= (int)$shareUser['id'] ?>"
                >

                    <?= htmlspecialchars(
                        $shareUserName
                    ) ?>

                    <?php if (!empty($shareUser['email'])) { ?>

                        (<?= htmlspecialchars(
                            $shareUser['email']
                        ) ?>)

                    <?php } ?>

                </option>

            <?php } ?>

        </select>


        <small>

            Hold CTRL/CMD to select multiple users.

        </small>



        <!-- SHARE ACCESS PERMISSIONS -->

        <div class="permission-options">


            <label>

                <input
                    type="checkbox"
                    id="shareView"
                >

                Can View

            </label>


            <label>

                <input
                    type="checkbox"
                    id="shareEdit"
                >

                Can Edit

            </label>


            <label>

                <input
                    type="checkbox"
                    id="shareShare"
                >

                Can Share

            </label>


        </div>



        <!-- SAVE -->

        <button
            type="button"
            class="save-btn"
            onclick="saveSharing()"
        >

            <i class="fa fa-save"></i>

            Save Permissions

        </button>


    </div>

</div>

<?php } ?>



<script src="assets/js/permissions.js"></script>


<?php

include "includes/footer.php";

?>