<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');


if(!Auth::isAdmin()){

    echo json_encode([
        "success"=>false,
        "message"=>"Access denied"
    ]);

    exit;

}


$user_id = $_POST['user_id'] ?? null;


if(!$user_id){

    echo json_encode([
        "success"=>false,
        "message"=>"User ID required"
    ]);

    exit;

}


// Delete permission record

$result = deleteData(
    "permissions",
    [
        "user_id"=>$user_id
    ]
);


echo json_encode([
    "success" => $result['success'],
    "message" => $result['success']
        ? "Permissions removed successfully."
        : $result['error']
]);
?>