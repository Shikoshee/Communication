<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();
if (!Permission::canUpload()) {
    echo json_encode([
        'success'=>false,
        'message'=>'Permission denied'
    ]);
    exit;
}

header('Content-Type: application/json');



if(
!isset($_POST['document_id']) ||
!isset($_POST['user_id'])
){


echo json_encode([


"success"=>false,


"message"=>"Required data missing"


]);


exit;


}



$document_id=intval($_POST['document_id']);

$user_id=intval($_POST['user_id']);

$permission=$_POST['permission'] ?? 'read';





// Check document


$document = fetchRow("

SELECT id,title

FROM documents

WHERE id=?

",
[
$document_id
]

);



if(!$document){


echo json_encode([


"success"=>false,


"message"=>"Document not found"


]);


exit;


}






// Insert sharing record


$result = insertData(

"document_sharing",

[

"document_id"=>$document_id,

"user_id"=>$user_id,

"permission"=>$permission

]

);





if($result['success']){


echo json_encode([


"success"=>true,


"message"=>"Document shared successfully"


]);



}else{


echo json_encode([


"success"=>false,


"message"=>$result['error']


]);


}