<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');

if(!Auth::isAdmin()){

    echo json_encode([
        "success"=>false,
        "message"=>"Access denied."
    ]);

    exit;

}

$document_id = (int)($_POST['document_id'] ?? 0);

$departments = $_POST['departments'] ?? [];

if(!$document_id || empty($departments)){

    echo json_encode([
        "success"=>false,
        "message"=>"Document and department are required."
    ]);

    exit;

}

$permissions = [

    "can_view" => (int)($_POST['can_view'] ?? 0),

    "can_edit" => (int)($_POST['can_edit'] ?? 0),

    "can_share" => (int)($_POST['can_share'] ?? 0)

];

foreach($departments as $department_id){

    $existing = fetchRow(

        "SELECT id
         FROM document_permissions
         WHERE document_id=?
         AND department_id=?",

        [
            $document_id,
            $department_id
        ]

    );

    if($existing){

        updateData(

            "document_permissions",

            $permissions,

            [

                "document_id"=>$document_id,

                "department_id"=>$department_id

            ]

        );

    }else{

        insertData(

            "document_permissions",

            [

                "document_id"=>$document_id,

                "department_id"=>$department_id,

                "can_view"=>$permissions['can_view'],

                "can_edit"=>$permissions['can_edit'],

                "can_share"=>$permissions['can_share']

            ]

        );

    }

}

echo json_encode([

    "success"=>true,

    "message"=>"Document permissions saved successfully."

]);