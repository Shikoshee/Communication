<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$tempPassword="Password@123";

$data=[

"first_name"=>trim($_POST['first_name']),
"last_name"=>trim($_POST['last_name']),
"username"=>trim($_POST['username']),
"email"=>trim($_POST['email']),
"department_id"=>$_POST['department_id'],
"role"=>$_POST['role'],
"status"=>$_POST['status'],
"password"=>password_hash($tempPassword,PASSWORD_DEFAULT)

];

$result=insertData("users",$data);

echo json_encode([

"success"=>$result['success'],

"message"=>$result['success']
?"User created successfully. Temporary password: ".$tempPassword
:$result['error']

]);