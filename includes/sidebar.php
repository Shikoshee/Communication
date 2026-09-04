<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Permission.php';

$user = Auth::getCurrentUser();

$role = strtolower(
    trim(
        (string)($user['role'] ?? '')
    )
);


/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
*/

$isAdmin = in_array(
    $role,
    [
        'admin',
        'administrator'
    ],
    true
);

$isManager = (
    $role === 'manager'
    ||
    str_contains($role, 'manager')
);


/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
| These come directly from the permissions table.
|
*/

$canApprove = Permission::canApprove();


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

?>

<aside class="sidebar">

<!-- ==========================================================
     LOGO
=========================================================== -->

<div class="logo">

    <button
        id="menuBtn"
        type="button"
    >

        <i class="fas fa-bars"></i>

    </button>

    <i class="fas fa-comments logo-icon"></i>

    <h2>
        Communication
    </h2>

</div>


<!-- ==========================================================
     NAVIGATION
=========================================================== -->

<ul>


    <!-- ======================================================
         DASHBOARD
    ======================================================= -->

    <li>

        <a
            href="/Communication/dashboard.php"
            title="Dashboard"
        >

            <i class="fas fa-home"></i>

            <span>
                Dashboard
            </span>

        </a>

    </li>


    <!-- ======================================================
         DOCUMENTS
    ======================================================= -->

    <li>

        <a
            href="/Communication/documents.php"
            title="Documents"
        >

            <i class="fas fa-folder-open"></i>

            <span>
                Documents
            </span>

        </a>

    </li>


    <!-- ======================================================
         UPLOAD
    ======================================================= -->

    <!--
        Upload is available to everyone.
    -->

    <li>

        <a
            href="/Communication/upload.php"
            title="Upload Document"
        >

            <i class="fas fa-upload"></i>

            <span>
                Upload
            </span>

        </a>

    </li>


    <!-- ======================================================
         COMMUNICATION
    ======================================================= -->

    <li>

        <a
            href="/Communication/communication.php"
            title="Communication"
        >

            <i class="fas fa-comments"></i>

            <span>
                Communication
            </span>

        </a>

    </li>


    <!-- ======================================================
         MAIL
    ======================================================= -->

    <li>

        <a
            href="/Communication/mail/index.php"
            title="Mail"
        >

            <i class="fas fa-envelope"></i>

            <span>
                Mail
            </span>

        </a>

    </li>


    <!-- ======================================================
         ADMIN + MANAGER
    ======================================================= -->

    <?php if ($isAdmin || $isManager): ?>


        <!-- ==================================================
             PERMISSIONS
        =================================================== -->

        <li>

            <a
                href="/Communication/permissions.php"
                title="Permissions"
            >

                <i class="fas fa-user-shield"></i>

                <span>
                    Permissions
                </span>

            </a>

        </li>


    <?php endif; ?>


    <!-- ======================================================
         APPROVALS
    ======================================================= -->

    <?php if ($canApprove): ?>

        <li>

            <a
                href="/Communication/approvals.php"
                title="Document Approvals"
            >

                <i class="fas fa-check-circle"></i>

                <span>
                    Approvals
                </span>

            </a>

        </li>

    <?php endif; ?>


    <!-- ======================================================
         ADMIN + MANAGER
    ======================================================= -->

    <?php if ($isAdmin || $isManager): ?>


        <!-- ==================================================
             REPORTS
        =================================================== -->

        <li>

            <a
                href="/Communication/reports.php"
                title="Reports & Analytics"
            >

                <i class="fas fa-chart-bar"></i>

                <span>
                    Reports
                </span>

            </a>

        </li>


        <!-- ==================================================
             USERS
        =================================================== -->

        <li>

            <a
                href="/Communication/users.php"
                title="Users"
            >

                <i class="fas fa-users"></i>

                <span>
                    Users
                </span>

            </a>

        </li>


    <?php endif; ?>


    <!-- ======================================================
         ADMIN ONLY
    ======================================================= -->

    <?php if ($isAdmin): ?>


        <!-- ==================================================
             DEPARTMENTS
        =================================================== -->

        <li>

            <a
                href="/Communication/departments.php"
                title="Departments"
            >

                <i class="fas fa-building"></i>

                <span>
                    Departments
                </span>

            </a>

        </li>


    <?php endif; ?>


    <!-- ======================================================
         SETTINGS
    ======================================================= -->

    <li>

        <a
            href="/Communication/settings.php"
            title="Settings"
        >

            <i class="fas fa-cog"></i>

            <span>
                Settings
            </span>

        </a>

    </li>


    <!-- ======================================================
         CHANGE PASSWORD
    ======================================================= -->

    <li>

        <a
            href="/Communication/change-password.php"
            title="Change Password"
        >

            <i class="fa fa-key"></i>

            <span>
                Change Password
            </span>

        </a>

    </li>


    <!-- ======================================================
         RECYCLE BIN
    ======================================================= -->

    <li>

        <a
            href="/Communication/recycle-bin.php"
            title="Recycle Bin"
        >

            <i class="fa fa-trash"></i>

            <span>
                Recycle Bin
            </span>

        </a>

    </li>


    <!-- ======================================================
         LOGOUT
    ======================================================= -->

    <li>

        <a
            href="/Communication/logout.php"
            class="logout-link"
            title="Logout"
        >

            <i class="fas fa-sign-out-alt"></i>

            <span>
                Logout
            </span>

        </a>

    </li>


</ul>


</aside>
