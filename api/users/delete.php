<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id=(int)($_POST['id'] ?? 0);

$result = deleteData("users",[
    "id"=>$id
]);

echo json_encode([
    "success"=>$result['success'],
    "message"=>$result['success']
        ? "User deleted successfully."
        : $result['error']
]);