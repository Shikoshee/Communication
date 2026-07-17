<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";


Auth::protect();
if (!Permission::canUpload()) {
    echo json_encode([
        'success'=>false,
        'message'=>'Permission denied'
    ]);
    exit;
}

$user = Auth::getCurrentUser();


header("Content-Type: application/json");



if(
$user['role']!="admin"
&&
$user['role']!="manager"
){

echo json_encode([

"success"=>false,

"message"=>"Permission denied"

]);

exit;

}




$id=(int)$_POST['id'];



$document=fetchRow("

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




updateData(

"documents",

[

"status"=>"approved",

"reviewed_by"=>$user['id'],

"reviewed_at"=>date("Y-m-d H:i:s")

],

"id=?",

[$id]

);






insertData(

"activity_logs",

[

"user_id"=>$user['id'],

"activity"=>"Approved ".$document['title'],

"document_id"=>$id,

"department_id"=>$document['department_id'],

"activity_type"=>"approve"

]

);






insertData(

"notifications",

[

"user_id"=>$document['uploaded_by'],

"title"=>"Document Approved",

"message"=>"Your document ".$document['title']." has been approved.",

"type"=>"approval",

"related_document_id"=>$id

]

);






echo json_encode([

"success"=>true,

"message"=>"Document approved"

]);