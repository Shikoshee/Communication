<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


$id = (int)($_POST['id'] ?? 0);


if(!$id){

    echo json_encode([
        "success"=>false,
        "message"=>"Notification ID missing"
    ]);

    exit;

}



$result = updateData(
    "notifications",
    [
        "is_read" => 1,
        "read_at" => date("Y-m-d H:i:s")
    ],
    [
        "id" => $id,
        "user_id" => $user['id']
    ]
);



echo json_encode([

    "success"=>$result['success']

]);