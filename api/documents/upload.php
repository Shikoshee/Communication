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



if(!isset($_FILES['document'])){

echo json_encode([
"success"=>false,
"message"=>"No file uploaded"
]);

exit;

}



$title=$_POST['title'];

$description=$_POST['description'];

$department=$_POST['department_id'];

$tags=$_POST['tags'];

$approval=isset($_POST['approval']);





$file=$_FILES['document'];


$filename=time()."_".$file['name'];


$uploadPath="../../uploads/documents/".$filename;



move_uploaded_file(
$file['tmp_name'],
$uploadPath
);





$status=$approval ? "pending":"approved";





$result=insertData(

"documents",

[

"title"=>$title,

"description"=>$description,

"file_name"=>$filename,

"file_path"=>"uploads/documents/".$filename,

"file_size"=>$file['size'],

"file_type"=>$file['type'],

"department_id"=>$department,

"uploaded_by"=>$user['id'],

"version"=>"1.0",

"status"=>$status,

"tags"=>$tags

]

);






if(!$result['success']){


echo json_encode([

"success"=>false,

"message"=>$result['error']

]);


exit;

}





$documentId=$result['insert_id'];






// ACTIVITY LOG

insertData(

"activity_logs",

[

"user_id"=>$user['id'],

"activity"=>"Uploaded document ".$title,

"document_id"=>$documentId,

"department_id"=>$department,

"activity_type"=>"upload"

]

);








// NOTIFICATION FOR APPROVAL

if($approval){


insertData(

"notifications",

[

"user_id"=>$user['id'],

"title"=>"Document Approval Required",

"message"=>"Document ".$title." requires approval.",

"type"=>"approval",

"related_document_id"=>$documentId

]

);


}







echo json_encode([

"success"=>true,

"message"=>"Document uploaded successfully"

]);
