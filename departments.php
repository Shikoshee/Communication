
<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";


$pageTitle = "Departments";
$breadcrumb = "Dashboard / Departments";
$buttonText = "Add Department";
$buttonLink = "javascript:addDepartment();";


include "includes/page-header.php";


// ==============================
// SUMMARY STATISTICS
// ==============================

$totalDepartments = countRows("
    SELECT id 
    FROM departments
");


$activeDepartments = countRows("
    SELECT id
    FROM departments
    WHERE status='active'
");

$totalEmployees = countRows("
    SELECT id
    FROM users
    WHERE status='active'
");

$totalDocuments = countRows("
    SELECT id
    FROM documents
");



// ==============================
// DEPARTMENT CARDS
// ==============================

$departmentCards = fetchAll("

SELECT

d.id,
d.name,
d.status,

CONCAT(u.first_name,' ',u.last_name) AS head_name,

COUNT(DISTINCT emp.id) AS employee_count,

COUNT(DISTINCT doc.id) AS document_count


FROM departments d


LEFT JOIN users u
ON d.head_id = u.id


LEFT JOIN users emp
ON emp.department_id = d.id


LEFT JOIN documents doc
ON doc.department_id = d.id


GROUP BY d.id


ORDER BY d.name

");



// ==============================
// DEPARTMENT TABLE
// ==============================

$departments = fetchAll("

SELECT

d.id,
d.name,
d.status,

CONCAT(u.first_name,' ',u.last_name) AS department_head,

COUNT(DISTINCT emp.id) AS employee_count,

COUNT(DISTINCT doc.id) AS document_count


FROM departments d


LEFT JOIN users u
ON d.head_id=u.id


LEFT JOIN users emp
ON emp.department_id=d.id


LEFT JOIN documents doc
ON doc.department_id=d.id


GROUP BY d.id


ORDER BY d.name

");


// ==============================
// CHART DATA
// ==============================

$chartData = fetchAll("

SELECT

d.name,

COUNT(doc.id) AS total_documents


FROM departments d


LEFT JOIN documents doc

ON doc.department_id = d.id


GROUP BY d.id, d.name


ORDER BY d.name

");

$chartLabels = [];
$chartValues = [];


foreach($chartData as $row){

    $chartLabels[] = $row['name'];

    $chartValues[] = (int)$row['total_documents'];

}

?>


<link rel="stylesheet" href="assets/css/departments.css">


<!-- SUMMARY CARDS -->

<div class="department-summary">


<div class="summary-card blue">

<i class="fa-solid fa-building"></i>

<div>

<h2><?= $totalDepartments ?></h2>

<p>Total Departments</p>

</div>

</div>



<div class="summary-card green">

<i class="fa-solid fa-circle-check"></i>

<div>

<h2><?= $activeDepartments ?></h2>

<p>Active Departments</p>

</div>

</div>




<div class="summary-card orange">

<i class="fa-solid fa-users"></i>

<div>

<h2><?= $totalEmployees ?></h2>

<p>Employees</p>

</div>

</div>




<div class="summary-card red">

<i class="fa-solid fa-folder"></i>

<div>

<h2><?= $totalDocuments ?></h2>

<p>Documents</p>

</div>

</div>



</div>





<!-- SEARCH -->

<div class="department-toolbar">


<input

type="text"

id="departmentSearch"

placeholder="Search department...">



<select id="departmentFilter">

<option value="">All Departments</option>

<?php foreach($departmentCards as $dept){ ?>

<option value="<?= strtolower($dept['name']) ?>">
<?= htmlspecialchars($dept['name']) ?>
</option>

<?php } ?>

</select>

</select>


</div>





<!-- DEPARTMENT CARDS -->

<?php

function departmentIcon($name){

    $name = strtolower($name);

    if(str_contains($name,'finance')){
        return "fa-money-bill-trend-up finance";
    }

    if(str_contains($name,'human') || str_contains($name,'hr')){
        return "fa-user-group hr";
    }

    if(str_contains($name,'ict') || str_contains($name,'technology')){
        return "fa-computer ict";
    }

    if(str_contains($name,'supply') || str_contains($name,'procurement')){
        return "fa-truck-fast supply";
    }

    if(str_contains($name,'quality')){
        return "fa-shield-halved quality";
    }

    if(str_contains($name,'sales')){
        return "fa-chart-line sales";
    }

    if(str_contains($name,'production')){
        return "fa-industry production";
    }

    if(str_contains($name,'marketing')){
        return "fa-bullhorn marketing";
    }

    return "fa-building general";

}

?>

<div class="department-grid">


<?php foreach($departmentCards as $card){ ?>


<div class="department-card"
onclick="viewDepartment(<?= $card['id'] ?>)"
data-department="<?= strtolower($card['name']) ?>">


<?php
list($iconClass, $colorClass) = explode(' ', departmentIcon($card['name']));
?>

<i class="fa-solid <?= $iconClass ?> icon <?= $colorClass ?>"></i>
<h3>

<?= htmlspecialchars($card['name']) ?>

</h3>
<p>

<strong>Head:</strong>

<?= htmlspecialchars($card['head_name'] ?? '-') ?>

</p>



<p>

<strong>Employees:</strong>

<?= $card['employee_count'] ?>

</p>



<p>

<strong>Documents:</strong>

<?= $card['document_count'] ?>

</p>



<span class="status <?= strtolower($card['status']) ?>">

<?= ucfirst($card['status']) ?>

</span>


</div>


<?php } ?>


</div>







<!-- TABLE -->


<div class="department-table">


<h3>Department Directory</h3>


<table>


<thead>
<tr>
<th>Department</th>

<th>Head</th>

<th>Employees</th>

<th>Documents</th>

<th>Status</th>

<th>Actions</th>


</tr>

</thead>



<tbody id="departmentTable">


<?php foreach($departments as $department){ ?>


<tr data-department="<?= strtolower($department['name']) ?>">


<td>

<?= htmlspecialchars($department['name']) ?>

</td>



<td>

<?= htmlspecialchars($department['department_head'] ?? '-') ?>

</td>



<td>

<?= $department['employee_count'] ?>

</td>



<td>

<?= $department['document_count'] ?>

</td>

<td>


<span class="status <?= strtolower($department['status']) ?>">


<?= ucfirst($department['status']) ?>


</span>


</td>



<td>


<button

class="edit-btn"

onclick="editDepartment(<?= $department['id'] ?>)">

<i class="fa fa-edit"></i>

</button>



<button

class="delete-btn"

onclick="deleteDepartment(

<?= $department['id'] ?>,

'<?= htmlspecialchars($department['name'],ENT_QUOTES) ?>'

)">


<i class="fa fa-trash"></i>


</button>



</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>






<!-- CHART -->


<div class="department-chart">


<h3>Documents by Department</h3>


<canvas id="departmentStatistics"></canvas>


</div>




<script>


const departmentLabels = <?= json_encode($chartLabels) ?>;


const departmentDocuments = <?= json_encode($chartValues) ?>;



</script>

<script>

const departmentLabels = <?= json_encode($chartLabels) ?>;

const departmentDocuments = <?= json_encode($chartValues) ?>;

</script>

<script src="assets/js/departments.js"></script>



<?php

include "includes/footer.php";

?>
```
