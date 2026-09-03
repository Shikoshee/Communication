<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";


Auth::protect();


header("Content-Type: application/json");


$id=(int)$_POST['id'];


if(!isset($_FILES['signed_file'])){

echo json_encode([

"success"=>false,

"message"=>"No file uploaded"

]);

exit;

}



$file=$_FILES['signed_file'];


$name=time()."_".$file['name'];


$path="../../uploads/documents/".$name;



move_uploaded_file(

$file['tmp_name'],

$path

);



updateData(

"documents",

[

"reviewed_file"=>"uploads/documents/".$name

],

[

"id"=>$id

]

);



echo json_encode([

"success"=>true,

"message"=>"Signed copy uploaded"

]);