<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');

if(!Auth::isAdmin()){

    echo json_encode([
        "success" => false,
        "message" => "Access denied"
    ]);

    exit;

}

$user_id = (int)($_GET['id'] ?? 0);

if(!$user_id){

    echo json_encode([
        "success" => false,
        "message" => "Invalid user."
    ]);

    exit;

}

$permission = fetchRow(

    "SELECT
        can_view,
        can_edit,
        can_approve,
        can_delete,
        can_share
     FROM permissions
     WHERE user_id=?",

    [$user_id]

);

if(!$permission){

    $permission = [

        "can_view" => 0,
        "can_edit" => 0,
        "can_approve" => 0,
        "can_delete" => 0,
        "can_share" => 0

    ];

}

echo json_encode([

    "success" => true,

    "permissions" => $permission

]);

?>