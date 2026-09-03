<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


$result = updateData(
    "notifications",
    [
        "is_read" => 1,
        "read_at" => date("Y-m-d H:i:s")
    ],
    [
        "user_id" => $user['id']
    ]
);


echo json_encode([

    "success"=>$result['success']

]);
