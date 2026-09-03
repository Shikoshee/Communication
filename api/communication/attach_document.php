<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$conversationId = isset($_POST["conversation_id"])
    ? (int)$_POST["conversation_id"]
    : 0;

$documentId = isset($_POST["document_id"])
    ? (int)$_POST["document_id"]
    : 0;

if($conversationId <= 0 || $documentId <= 0){

    echo json_encode([

        "success"=>false,
        "message"=>"Invalid request."

    ]);

    exit;

}

/*
|--------------------------------------------------------------------------
| Get Document
|--------------------------------------------------------------------------
*/

$document = fetchRow(

"
SELECT

title

FROM documents

WHERE id=?

",

[
$documentId
]

);

if(!$document){

    echo json_encode([

        "success"=>false,
        "message"=>"Document not found."

    ]);

    exit;

}

/*
|--------------------------------------------------------------------------
| Save Message
|--------------------------------------------------------------------------
*/

$result = insertData(

"messages",

[

    "conversation_id"=>$conversationId,

    "sender_id"=>$user["id"],

    "message"=>"Shared a document.",

    "document_id"=>$documentId

]

);

echo json_encode([

    "success"=>$result["success"]

]);
insertData(

"notifications",

[

    "user_id"=>$conversationId,

    "title"=>"Document Shared",

    "message"=>$user["first_name"]." shared ".$document["title"],

    "type"=>"sharing"

]

);