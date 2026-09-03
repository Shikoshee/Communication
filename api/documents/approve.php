<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Permission.php";
require_once "../../includes/notifications.php";

Auth::protect();

header("Content-Type: application/json");

if(!Permission::canApprove()){

    echo json_encode([
        "success"=>false,
        "message"=>"Permission denied"
    ]);

    exit;
}

$user = Auth::getCurrentUser();

$id = (int)($_POST["id"] ?? 0);

$document = fetchRow(

    "
    SELECT *
    FROM documents
    WHERE id=?
    ",

    [$id]

);

if(!$document){

    echo json_encode([
        "success"=>false,
        "message"=>"Document not found"
    ]);

    exit;
}

if(empty($document["reviewed_file"])){

    echo json_encode([
        "success"=>false,
        "message"=>"Please upload the signed copy before approving."
    ]);

    exit;
}

$result = updateData(

    "documents",

    [

        "status"=>"approved",

        "reviewed_by"=>$user["id"],

        "reviewed_at"=>date("Y-m-d H:i:s")

    ],

    [

        "id"=>$id

    ]

);

if(!$result["success"]){

    echo json_encode([
        "success"=>false,
        "message"=>"Approval failed."
    ]);

    exit;
}

insertData(

    "activity_logs",

    [

        "user_id"=>$user["id"],

        "activity"=>"Approved ".$document["title"],

        "document_id"=>$id,

        "department_id"=>$document["department_id"],

        "activity_type"=>"approve"

    ]

);

createNotification(

    $document["uploaded_by"],

    "Document Approved",

    "Your document '".$document["title"]."' was reviewed, signed and approved.",

    "approval",

    $id

);

echo json_encode([

    "success"=>true,

    "message"=>"Document approved successfully."

]);