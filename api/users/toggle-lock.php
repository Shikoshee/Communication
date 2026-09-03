<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id=(int)$_POST['id'];

$user=fetchRow("

SELECT status

FROM users

WHERE id=?

",[$id]);

$newStatus=
$user['status']=="locked"
?"active"
:"locked";

$result=updateData("users",[

"status"=>$newStatus

],[

"id"=>$id

]);

echo json_encode([

"success"=>$result['success'],

"message"=>$result['success']
?"User status changed to ".$newStatus
:$result['error']

]);