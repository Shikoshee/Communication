<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");


$id = intval($_GET['id'] ?? 0);


if($id <= 0){

    echo json_encode([
        "success"=>false,
        "message"=>"Invalid department."
    ]);

    exit;

}



// Department information

$department = fetchRow("

SELECT

d.*,

CONCAT(
u.first_name,
' ',
u.last_name
) AS head_name


FROM departments d


LEFT JOIN users u

ON d.head_id=u.id


WHERE d.id=?

",

[$id]

);



if(!$department){

    echo json_encode([
        "success"=>false,
        "message"=>"Department not found."
    ]);

    exit;

}



// Employees

$employees = fetchAll("

SELECT

id,

CONCAT(
first_name,
' ',
last_name
) AS name,

email,

role


FROM users


WHERE department_id=?


ORDER BY first_name

",

[$id]

);



// Documents

$documents = fetchAll("

SELECT

id,

title,

created_at


FROM documents


WHERE department_id=?


ORDER BY created_at DESC


LIMIT 10

",

[$id]

);



// Activity

$activities = fetchAll("

SELECT

activity,

created_at


FROM activity_logs


WHERE department_id=?


ORDER BY created_at DESC


LIMIT 10

",

[$id]

);



echo json_encode([

"success"=>true,

"department"=>$department,

"employees"=>$employees,

"documents"=>$documents,

"activities"=>$activities

]);