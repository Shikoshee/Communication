<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');


if (!Auth::isAdmin()) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied"
    ]);

    exit;

}



$user_id = $_POST['user_id'] ?? null;


if (!$user_id) {

    echo json_encode([
        "success" => false,
        "message" => "User ID required"
    ]);

    exit;

}



$data = [];

$permissions = [
    "can_view",
    "can_edit",
    "can_approve",
    "can_delete",
    "can_share"
];

foreach($permissions as $permission){

    if(isset($_POST[$permission])){

        $data[$permission] = (int)$_POST[$permission];

    }

}


// Check if permission exists

$existing = fetchRow(

    "SELECT id FROM permissions WHERE user_id=?",

    [$user_id]

);



if ($existing) {


    $result = updateData(

        "permissions",

        $data,

        ["user_id"=>$user_id]

    );


} else {


    $data['user_id'] = $user_id;


    $result = insertData(

        "permissions",

        $data

    );

}



echo json_encode($result);

?>