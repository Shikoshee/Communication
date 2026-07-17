<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";


Auth::protect();


header("Content-Type: application/json");


$user = Auth::getCurrentUser();


$currentPassword = $_POST['current_password'] ?? '';

$newPassword = $_POST['new_password'] ?? '';



if(empty($currentPassword) || empty($newPassword)){


echo json_encode([

"success"=>false,

"message"=>"All fields are required"

]);

exit();

}



$query="

SELECT password

FROM users

WHERE id=?

";


$stmt=$conn->prepare($query);

$stmt->bind_param(
"i",
$user['id']
);

$stmt->execute();


$result=$stmt->get_result();

$data=$result->fetch_assoc();



if(!$data){


echo json_encode([

"success"=>false,

"message"=>"User not found"

]);


exit();

}




if(!password_verify($currentPassword,$data['password'])

&& hash('sha256',$currentPassword)!=$data['password']){


echo json_encode([

"success"=>false,

"message"=>"Current password incorrect"

]);


exit();

}




$newHash=password_hash(
$newPassword,
PASSWORD_DEFAULT
);



$update=$conn->prepare("

UPDATE users SET

password=?,

must_change_password=0

WHERE id=?

");


$update->bind_param(

"si",

$newHash,

$user['id']

);



if($update->execute()){


Auth::logActivity(

$user['id'],

"Password changed",

null,

null,

"edit"

);



echo json_encode([

"success"=>true,

"message"=>"Password changed successfully"

]);


}else{


echo json_encode([

"success"=>false,

"message"=>"Failed to update password"

]);


}
