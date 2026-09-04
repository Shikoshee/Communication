<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
Auth::protect();


$user = Auth::getCurrentUser();


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";


// ==============================
// LOAD DEPARTMENTS
// ==============================

$departments = fetchAll("

SELECT id,name
FROM departments
ORDER BY name

");


// ==============================
// LOAD USERS FOR SHARING
// ==============================

$users = fetchAll("

SELECT 
id,
CONCAT(first_name,' ',last_name) AS name,
email

FROM users

WHERE status='active'

ORDER BY first_name

");


?>


<link rel="stylesheet" href="assets/css/upload.css">



<div class="page-header">

<div>

<h1>
Upload Document
</h1>

<p>
Add new documents to the communication system.
</p>

</div>

</div>

<div class="upload-container">

<form id="uploadForm" enctype="multipart/form-data">

<div class="form-group">

<label>
Document Title
</label>


<input 
type="text"
id="title"
name="title"
required>

</div>





<div class="form-group">

    <label>
        Departments
    </label>

    <select
        id="department"
        name="department_id[]"
        multiple
        required
    >

        <?php foreach($departments as $dept){ ?>

            <option value="<?= $dept['id'] ?>">
                <?= htmlspecialchars($dept['name']) ?>
            </option>

        <?php } ?>

    </select>

    <small>
        Hold CTRL (Windows) or CMD (Mac) to select multiple departments
    </small>

</div>


<div class="form-group">

<label>
Description
</label>


<textarea

id="description"

name="description"

rows="5">

</textarea>


</div>

<div class="form-group">

<label>
Tags
</label>

<input
type="text"

id="tags"

name="tags">


</div>


<div class="form-group">

<label>
Select File
</label>

<div class="file-upload">

<i class="fa fa-cloud-upload"></i>

<input

type="file"

id="documentFile"

name="document"

required>


</div>


<p id="fileName"></p>


</div>







<div class="form-group checkbox">


<input

type="checkbox"

id="approval"

name="approval">


<label>

Send for approval after upload

</label>


</div>







<div class="form-group">


<label>
Share With
</label>



<select 

multiple

id="shareUsers"

name="shareUsers[]">



<?php foreach($users as $shareUser){ ?>


<option value="<?= $shareUser['id'] ?>">

<?= htmlspecialchars($shareUser['name']) ?>

(<?= htmlspecialchars($shareUser['email']) ?>)

</option>


<?php } ?>


</select>



<small>
Hold CTRL to select multiple users
</small>


</div>








<div class="progress-container">


<div id="progressBar"></div>


</div>







<button 
type="submit"

class="upload-submit">


<i class="fa fa-upload"></i>

Upload Document


</button>



</form>


</div>







<script src="assets/js/upload.js"></script>



<?php

include "includes/footer.php";

?>