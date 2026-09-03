<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/notifications.php";

Auth::protect();

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



$id = (int)($_POST['id'] ?? 0);

$reason = $_POST['reason'] ?? '';



$document = fetchRow("

SELECT *

FROM documents

WHERE id=?

",
[
$id
]);



if(!$document){

    echo json_encode([

        "success"=>false,

        "message"=>"Document not found"

    ]);

    exit;

}




$result = updateData(

"documents",

[

"status"=>"rejected",

"reviewed_by"=>$user['id'],

"reviewed_at"=>date("Y-m-d H:i:s"),

"reviewer_comment"=>$reason

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





insertData(

"activity_logs",

[

"user_id"=>$user['id'],

"activity"=>"Rejected ".$document['title'],

"document_id"=>$id,

"department_id"=>$document['department_id'],

"activity_type"=>"reject"

]

);





createNotification(

    $document['uploaded_by'],

    "Document Rejected",

    "Your document '".$document['title']."' has been rejected. Reason: ".$reason,

    "approval",

    $id

);



echo json_encode([

"success"=>true,

"message"=>"Document rejected successfully"

]);