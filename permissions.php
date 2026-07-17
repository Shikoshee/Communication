<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Permission.php';

Auth::protect();

if (!Auth::isAdmin()) {
    die("Access Denied");
}

$user = Auth::getCurrentUser();


// ======================================
// SUMMARY
// ======================================

$totalUsers = countRows("
    SELECT id
    FROM users
");

$totalAdmins = countRows("
    SELECT id
    FROM users
    WHERE role='admin'
");

$totalDepartments = countRows("
    SELECT id
    FROM departments
");

$usersWithPermissions = countRows("
    SELECT DISTINCT user_id
    FROM permissions
");


// ======================================
// DOCUMENTS
// ======================================

$documents = fetchAll("

SELECT

id,
title

FROM documents

ORDER BY title

");


// ======================================
// DEPARTMENTS
// ======================================

$departments = fetchAll("

SELECT

id,
name

FROM departments

ORDER BY name

");


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/permissions.css">

<div class="page-header">

    <div>

        <h1>Permissions Management</h1>

        <p>
            Control who can view, edit, approve, delete and share documents.
        </p>

    </div>

</div>



<!-- ===================== -->
<!-- SUMMARY -->
<!-- ===================== -->

<div class="permission-cards">

    <div class="permission-card blue">

        <i class="fa fa-users"></i>

        <div>

            <h2><?= $totalUsers ?></h2>

            <p>Total Users</p>

        </div>

    </div>


    <div class="permission-card green">

        <i class="fa fa-user-shield"></i>

        <div>

            <h2><?= $totalAdmins ?></h2>

            <p>Administrators</p>

        </div>

    </div>


    <div class="permission-card purple">

        <i class="fa fa-user-check"></i>

        <div>

            <h2><?= $usersWithPermissions ?></h2>

            <p>Users With Permissions</p>

        </div>

    </div>


    <div class="permission-card orange">

        <i class="fa fa-building"></i>

        <div>

            <h2><?= $totalDepartments ?></h2>

            <p>Departments</p>

        </div>

    </div>

</div>



<!-- ===================== -->
<!-- PERMISSIONS TABLE -->
<!-- ===================== -->

<div class="permission-container">

    <div class="table-header">

        <h3>User Access Permissions</h3>

        <button
            class="add-user-btn"
            onclick="loadPermissions()">

            <i class="fa fa-refresh"></i>

            Refresh Permissions

        </button>

    </div>

    <table>

        <thead>

            <tr>

                <th>User</th>

                <th>Department</th>

                <th>View</th>

                <th>Edit</th>

                <th>Approve</th>

                <th>Delete</th>

                <th>Share</th>

                <th>Actions</th>

            </tr>

        </thead>

        <tbody id="permissionsTable">

        </tbody>

    </table>

</div>



<!-- ===================== -->
<!-- DOCUMENT SHARING -->
<!-- ===================== -->

<div class="share-container">

    <h3>Share Document Permissions</h3>

    <div class="share-box">

        <label>

            Select Document

        </label>

        <select id="documentSelect">

            <option value="">Select Document</option>

            <?php foreach($documents as $doc){ ?>

                <option value="<?= $doc['id'] ?>">

                    <?= htmlspecialchars($doc['title']) ?>

                </option>

            <?php } ?>

        </select>



        <label>

            Allow Access To

        </label>

        <select
            id="departmentSelect"
            multiple>

            <?php foreach($departments as $department){ ?>

                <option value="<?= $department['id'] ?>">

                    <?= htmlspecialchars($department['name']) ?>

                </option>

            <?php } ?>

        </select>



        <div class="permission-options">

            <label>

                <input
                    type="checkbox"
                    id="shareView">

                Can View

            </label>

            <label>

                <input
                    type="checkbox"
                    id="shareEdit">

                Can Edit

            </label>

            <label>

                <input
                    type="checkbox"
                    id="shareShare">

                Can Share

            </label>

        </div>



        <button
            class="save-btn"
            onclick="saveSharing()">

            <i class="fa fa-save"></i>

            Save Permissions

        </button>

    </div>

</div>

<script src="assets/js/permissions.js"></script>

<?php include "includes/footer.php"; ?>