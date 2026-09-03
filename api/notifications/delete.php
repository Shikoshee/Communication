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


$result = deleteData(
    "notifications",
    [
        "id" => $id,
        "user_id" => $user['id']
    ]
);


if ($result['success']) {

    echo json_encode([
        "success" => true,
        "message" => "Notification deleted successfully."
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => $result['error']
    ]);

}
;
