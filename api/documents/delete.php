<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

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


header('Content-Type: application/json');





if(!isset($_POST['id'])){


echo json_encode([

"success"=>false,

"message"=>"Document ID missing"

]);


exit;

}





$documentId = (int)$_POST['id'];




// ==============================
// GET DOCUMENT DETAILS
// ==============================


$document = fetchRow("

SELECT *

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

"message"=>"Document not found"

]);


exit;


}







// ==============================
// DELETE FILE
// ==============================


$file = "../../".$document['file_path'];



if(file_exists($file)){


unlink($file);


}







// ==============================
// DELETE DATABASE RECORD
// ==============================


$result = deleteData(

"documents",

[

"id"=>$documentId

]

);





if(!$result['success']){


echo json_encode([

"success"=>false,

"message"=>$result['error']

]);


exit;


}








// ==============================
// ACTIVITY LOG
// ==============================


insertData(

"activity_logs",

[

"user_id"=>$user['id'],

"activity"=>
"Deleted document ".$document['title'],

"document_id"=>$documentId,

"department_id"=>$document['department_id'],

"activity_type"=>"edit"

]

);








// ==============================
// NOTIFICATION
// ==============================


insertData(

"notifications",

[

"user_id"=>$user['id'],

"title"=>"Document Deleted",

"message"=>
"Document ".$document['title']." was deleted.",

"type"=>"system",

"related_document_id"=>$documentId

]

);








echo json_encode([

"success"=>true,

"message"=>"Document deleted successfully"

]);
