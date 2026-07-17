<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$id = (int)($_POST['id'] ?? 0);

$name = trim($_POST['name'] ?? '');

$description = trim($_POST['description'] ?? '');

$status = strtolower(trim($_POST['status'] ?? 'active'));

if ($id <= 0 || $name == '') {

    echo json_encode([
        "success" => false,
        "message" => "Invalid department."
    ]);

    exit;
}

$exists = fetchRow(

    "SELECT id FROM departments WHERE name=? AND id<>?",

    [$name,$id]

);

if($exists){

    echo json_encode([
        "success"=>false,
        "message"=>"Department name already exists."
    ]);

    exit;
}

$result = updateData(

    "departments",

    [

        "name"=>$name,

        "description"=>$description,

        "status"=>$status

    ],

    [

        "id"=>$id

    ]

);

if(!$result['success']){

    echo json_encode([
        "success"=>false,
        "message"=>$result['error']
    ]);

    exit;
}

insertData("activity_logs",[

    "user_id"=>$user['id'],

    "department_id"=>$id,

    "activity"=>"Updated department ".$name,

    "activity_type"=>"edit"

]);

echo json_encode([

    "success"=>true,

    "message"=>"Department updated successfully."

]);