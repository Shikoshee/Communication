<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$id = intval($_POST['id'] ?? 0);

if($id<=0){

    echo json_encode([
        "success"=>false,
        "message"=>"Invalid department."
    ]);

    exit;
}

$department = fetchRow(

"SELECT * FROM departments WHERE id=?",

[$id]

);

if(!$department){

    echo json_encode([
        "success"=>false,
        "message"=>"Department not found."
    ]);

    exit;
}

$result = deleteData(

"departments",

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

"activity"=>"Deleted Department: ".$department['name'],

"activity_type"=>"edit"

]);

echo json_encode([

"success"=>true,

"message"=>"Department deleted successfully."

]);
