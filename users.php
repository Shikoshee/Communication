<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();
$user = Auth::getCurrentUser();

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

$pageTitle = "Users Management";
$breadcrumb = "Dashboard / Users";
$buttonText = "Add User";
$buttonLink = "javascript:addUser();";

include "includes/page-header.php";


// =====================================
// SUMMARY
// =====================================

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


// =====================================
// DEPARTMENTS
// =====================================

$departments = fetchAll("
    SELECT id,name
    FROM departments
    ORDER BY name
");


// =====================================
// USERS
// =====================================

$users = fetchAll("

SELECT

u.*,

d.name AS department_name

FROM users u

LEFT JOIN departments d
ON d.id=u.department_id

ORDER BY

u.first_name,
u.last_name

");


// =====================================
// CHART
// =====================================

$chartData = fetchAll("

SELECT

d.name,
COUNT(u.id) total_users

FROM departments d

LEFT JOIN users u
ON u.department_id=d.id

GROUP BY d.id

ORDER BY d.name

");

$chartLabels = [];
$chartValues = [];

foreach($chartData as $row){

    $chartLabels[] = $row['name'];
    $chartValues[] = (int)$row['total_users'];

}

?>

<link rel="stylesheet" href="assets/css/users.css">


<!-- ===================== -->
<!-- SUMMARY -->
<!-- ===================== -->

<div class="user-summary">

    <div class="summary-card blue">
        <i class="fa-solid fa-users"></i>
        <div>
            <h2><?= $totalUsers ?></h2>
            <p>Total Users</p>
        </div>
    </div>

    <div class="summary-card green">
        <i class="fa-solid fa-user-check"></i>
        <div>
            <h2><?= $activeUsers ?></h2>
            <p>Active Users</p>
        </div>
    </div>

    <div class="summary-card orange">
        <i class="fa-solid fa-user-xmark"></i>
        <div>
            <h2><?= $inactiveUsers ?></h2>
            <p>Inactive Users</p>
        </div>
    </div>

    <div class="summary-card red">
        <i class="fa-solid fa-user-lock"></i>
        <div>
            <h2><?= $lockedUsers ?></h2>
            <p>Locked Accounts</p>
        </div>
    </div>

</div>



<!-- ===================== -->
<!-- TOOLBAR -->
<!-- ===================== -->

<div class="user-toolbar">

    <input
        type="text"
        id="userSearch"
        placeholder="Search users...">

    <select id="departmentFilter">

        <option value="">All Departments</option>

        <?php foreach($departments as $department){ ?>

            <option value="<?= $department['id'] ?>">
                <?= htmlspecialchars($department['name']) ?>
            </option>

        <?php } ?>

    </select>

</div>



<!-- ===================== -->
<!-- TABLE -->
<!-- ===================== -->

<div class="user-table">

<h3>System Users</h3>

<table>

<thead>

<tr>

<th>Photo</th>
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

<?php foreach($users as $u){ ?>

<tr
data-name="<?= strtolower($u['first_name'].' '.$u['last_name']) ?>"
data-department="<?= $u['department_id'] ?>">

<td>

<img
class="profile-photo"
src="<?= !empty($u['profile_photo']) ? htmlspecialchars($u['profile_photo']) : 'assets/images/default-user.png' ?>">

</td>

<td>

<?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?>

</td>

<td>

<?= htmlspecialchars($u['email']) ?>

</td>

<td>

<?= htmlspecialchars($u['department_name'] ?? '-') ?>

</td>

<td>

<span class="role <?= strtolower($u['role']) ?>">

<?= ucfirst($u['role']) ?>

</span>

</td>

<td>

<span class="status <?= strtolower($u['status']) ?>">

<?= ucfirst($u['status']) ?>

</span>

</td>

<td>

<?= $u['last_login']
    ? date("d M Y H:i",strtotime($u['last_login']))
    : "-" ?>

</td>

<td>

<button class="view-btn"
onclick="viewUser(<?= $u['id'] ?>)">
<i class="fa fa-eye"></i>
</button>

<button
class="edit-btn"
onclick="editUser(<?= $u['id'] ?>)">
<i class="fa fa-edit"></i>
</button>

<button
class="reset-btn"
onclick="resetPassword(<?= $u['id'] ?>)">
<i class="fa fa-key"></i>
</button>

<button
class="lock-btn"
onclick="lockUser(<?= $u['id'] ?>)">
<i class="fa fa-lock"></i>
</button>

<button
class="delete-btn"
onclick="deleteUser(<?= $u['id'] ?>,'<?= htmlspecialchars($u['first_name'].' '.$u['last_name'],ENT_QUOTES) ?>')">
<i class="fa fa-trash"></i>
</button>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>



<!-- ===================== -->
<!-- CHART -->
<!-- ===================== -->

<div class="user-chart">

<h3>Users by Department</h3>

<canvas id="userChart"></canvas>

</div>



<script>

const userChartLabels = <?= json_encode($chartLabels) ?>;
const userChartValues = <?= json_encode($chartValues) ?>;

</script>

<script src="assets/js/users.js"></script>

<?php include "includes/footer.php"; ?>