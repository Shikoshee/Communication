<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();


$users=fetchAll("

SELECT 
id,
first_name,
last_name

FROM users

WHERE status='active'

ORDER BY first_name

");


echo json_encode($users);