<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id=(int)$_POST['id'];

$data=[

"first_name"=>trim($_POST['first_name']),
"last_name"=>trim($_POST['last_name']),
"username"=>trim($_POST['username']),
"email"=>trim($_POST['email']),
"role"=>$_POST['role'],
"status"=>$_POST['status']

];

$result=updateData("users",$data,[
"id"=>$id
]);

echo json_encode([

"success"=>$result['success'],

"message"=>$result['success']
?"User updated successfully."
:$result['error']

]);