<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

$user = Auth::getCurrentUser();

$department=(int)$_POST["department_id"];

$document=(int)$_POST["document_id"];

insertData("department_messages",[

"department_id"=>$department,

"user_id"=>$user["id"],

"message"=>"Shared a document.",

"attachment_document_id"=>$document

]);

echo json_encode([

"success"=>true

]);