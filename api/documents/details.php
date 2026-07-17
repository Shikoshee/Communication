<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();


header('Content-Type: application/json');



if(!isset($_GET['id'])){


echo json_encode([

"success"=>false,

"message"=>"Document ID missing"

]);


exit;


}



$id=intval($_GET['id']);




$document = fetchRow("

SELECT


d.*,


dep.name AS department_name,


CONCAT(
u.first_name,
' ',
u.last_name
) AS owner_name



FROM documents d



LEFT JOIN departments dep

ON dep.id=d.department_id



LEFT JOIN users u

ON u.id=d.uploaded_by



WHERE d.id=?


",
[
$id
]

);




if(!$document){


echo json_encode([


"success"=>false,


"message"=>"Document not found"


]);


exit;


}





echo json_encode([


"success"=>true,


"document"=>$document



]);