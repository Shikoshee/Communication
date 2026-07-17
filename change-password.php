<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/change-password.css">


<div class="page-header">

<h1>
Change Password
</h1>

<p>
Update your account password.
</p>

</div>



<div class="password-container">


<form id="changePasswordForm">


<div class="form-group">

<label>
Current Password
</label>

<input 
type="password"
id="currentPassword"
required>

</div>



<div class="form-group">

<label>
New Password
</label>

<input 
type="password"
id="newPassword"
required>

</div>



<div class="form-group">

<label>
Confirm New Password
</label>

<input 
type="password"
id="confirmPassword"
required>

</div>



<button type="submit">

<i class="fa fa-key"></i>

Change Password

</button>


</form>


</div>


<script src="assets/js/change-password.js"></script>


<?php

include "includes/footer.php";

?>