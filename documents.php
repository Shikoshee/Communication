<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Permission.php';

Auth::protect();
if (!Permission::canView()) {
    die("Access Denied");
}

$user = Auth::getCurrentUser();


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";



// ==============================
// STATISTICS
// ==============================


$totalDocuments = countRows("
SELECT id
FROM documents
");


$approvedDocuments = countRows("
SELECT id
FROM documents
WHERE status='approved'
");


$pendingDocuments = countRows("
SELECT id
FROM documents
WHERE status='pending'
");


$rejectedDocuments = countRows("
SELECT id
FROM documents
WHERE status='rejected'
");




// ==============================
// LOAD DOCUMENTS
// ==============================


$documents = fetchAll("

SELECT

doc.*,

d.name AS department_name,

CONCAT(
u.first_name,
' ',
u.last_name
) AS owner_name


FROM documents doc


LEFT JOIN departments d
ON d.id = doc.department_id


LEFT JOIN users u
ON u.id = doc.uploaded_by


ORDER BY doc.created_at DESC


");




// ==============================
// LOAD DEPARTMENTS FILTER
// ==============================


$departments = fetchAll("

SELECT id,name

FROM departments

ORDER BY name

");



?>


<link rel="stylesheet" href="assets/css/documents.css">



<div class="page-header">


<div>

<h1>
Documents Management
</h1>


<p>
Manage organizational documents, approvals and sharing.
</p>


</div>



<a href="upload.php" class="upload-btn">

<i class="fa fa-upload"></i>

Upload Document

</a>


</div>





<!-- SUMMARY -->


<div class="document-summary">


<div class="summary-card blue">

<i class="fa fa-file"></i>

<div>

<h2><?= $totalDocuments ?></h2>

<p>Total Documents</p>

</div>

</div>




<div class="summary-card green">

<i class="fa fa-check"></i>

<div>

<h2><?= $approvedDocuments ?></h2>

<p>Approved</p>

</div>

</div>




<div class="summary-card orange">

<i class="fa fa-clock"></i>

<div>

<h2><?= $pendingDocuments ?></h2>

<p>Pending Approval</p>

</div>

</div>




<div class="summary-card red">

<i class="fa fa-times"></i>

<div>

<h2><?= $rejectedDocuments ?></h2>

<p>Rejected</p>

</div>

</div>


</div>







<!-- FILTERS -->


<div class="document-controls">


<div class="search-box">

<i class="fa fa-search"></i>


<input

type="text"

id="documentSearch"

placeholder="Search documents...">


</div>




<select id="departmentFilter">


<option value="">
All Departments
</option>



<?php foreach($departments as $dept){ ?>


<option value="<?= strtolower($dept['name']) ?>">

<?= htmlspecialchars($dept['name']) ?>

</option>


<?php } ?>


</select>





<select id="statusFilter">


<option value="">
All Status
</option>

<option value="approved">
Approved
</option>

<option value="pending">
Pending
</option>

<option value="rejected">
Rejected
</option>


</select>



</div>









<div class="document-card">


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
Owner
</th>

<th>
Status
</th>

<th>
Version
</th>

<th>
Date
</th>

<th>
Actions
</th>


</tr>

</thead>



<tbody id="documentsTable">



<?php foreach($documents as $doc){ ?>


<tr

data-name="<?= strtolower($doc['title']) ?>"

data-department="<?= strtolower($doc['department_name']) ?>"

data-status="<?= strtolower($doc['status']) ?>">





<td>


<i class="fa 
<?php

$type=strtolower($doc['file_type']);


if(str_contains($type,'pdf')){

echo 'fa-file-pdf pdf';

}elseif(
str_contains($type,'word')
){

echo 'fa-file-word word';

}else{

echo 'fa-file';

}

?>">

</i>


<?= htmlspecialchars($doc['title']) ?>


</td>





<td>

<?= htmlspecialchars($doc['department_name']) ?>

</td>




<td>

<?= htmlspecialchars($doc['owner_name'] ?? '-') ?>

</td>





<td>


<span class="status <?= $doc['status'] ?>">

<?= ucfirst($doc['status']) ?>


</span>


</td>





<td>

v<?= htmlspecialchars($doc['version']) ?>

</td>




<td>

<?= date(
"d M Y",
strtotime($doc['created_at'])
) ?>


</td>






<td>


<a

href="<?= $doc['file_path'] ?>"

target="_blank"

class="action view">

<i class="fa fa-eye"></i>

</a>





<a

download

href="<?= $doc['file_path'] ?>"

class="action download">

<i class="fa fa-download"></i>

</a>



<?php if(
($user['role']=="admin" || $user['role']=="manager")
&&
$doc['status']=="pending"
){ ?>


<button

class="action approve"

onclick="approveDocument(
<?= $doc['id'] ?>
)">

<i class="fa fa-check"></i>

</button>



<button

class="action reject"

onclick="rejectDocument(
<?= $doc['id'] ?>
)">

<i class="fa fa-times"></i>

</button>


<?php } ?>

<button

class="action delete"

onclick="deleteDocument(
<?= $doc['id'] ?>,
'<?= htmlspecialchars($doc['title'],ENT_QUOTES) ?>'
)">


<i class="fa fa-trash"></i>


</button>




<button

class="action share"

onclick="shareDocument(
<?= $doc['id'] ?>
)">


<i class="fa fa-share"></i>


</button>




</td>



</tr>


<?php } ?>



</tbody>


</table>


</div>





<script src="assets/js/documents.js"></script>



<?php

include "includes/footer.php";

?>