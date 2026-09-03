<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id = (int)($_GET['id'] ?? 0);

$user = fetchRow("
    SELECT *
    FROM users
    WHERE id=?
", [$id]);

if(!$user){

    echo json_encode([
        "success"=>false,
        "message"=>"User not found."
    ]);

    exit;
}

echo json_encode([
    "success"=>true,
    "user"=>$user
]);