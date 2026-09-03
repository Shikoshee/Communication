<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";


Auth::protect();


$user=Auth::getCurrentUser();



$id=(int)$_POST['id'];



if(!isset($_FILES['signed_document'])){


echo json_encode([

"success"=>false,

"message"=>"No signed document uploaded"

]);


exit;

}



$file=$_FILES['signed_document'];



$name=time()."_".$file['name'];



$path="../../uploads/documents/".$name;



move_uploaded_file(

$file['tmp_name'],

$path

);




$result=updateData(

"documents",

[

"reviewed_file"=>"uploads/documents/".$name,

"reviewer_comment"=>$_POST['comment'],

"reviewed_by"=>$user['id']

],

"id=?",

[$id]

);



echo json_encode([

"success"=>$result['success']

]);