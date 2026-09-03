<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$user = Auth::getCurrentUser();


if(!$user){

    echo json_encode([

        "success" => false,

        "message" => "User session not found."

    ]);

    exit;

}


$userId = (int)(

    $user['id'] ?? 0

);


$userRole = strtolower(

    trim(

        (string)(

            $user['role'] ?? 'user'

        )

    )

);


/*
|--------------------------------------------------------------------------
| GET CURRENT USER DEPARTMENT
|--------------------------------------------------------------------------
|
| First try department_id from the authenticated user array.
|
| If it is not available, retrieve it directly from the database.
|
*/

$departmentId = (int)(

    $user['department_id'] ?? 0

);


if($departmentId <= 0){

    $departmentRow = fetchRow(

        "SELECT

            department_id

         FROM users

         WHERE id=?

         LIMIT 1",

        [
            $userId
        ]

    );


    $departmentId = (int)(

        $departmentRow['department_id'] ?? 0

    );

}


/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
|
| Administrators can see all users.
|
*/

$isAdmin = in_array(

    $userRole,

    [
        'admin',
        'administrator'
    ],

    true

);


/*
|--------------------------------------------------------------------------
| MANAGER
|--------------------------------------------------------------------------
|
| Managers can ONLY see users belonging
| to their own department.
|
*/

$isManager = (

    $userRole === 'manager'

);


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$query = "

SELECT

    users.id,

    users.first_name,

    users.last_name,

    users.username,

    users.role,

    users.department_id,

    departments.name AS department_name,


    COALESCE(
        permissions.can_view,
        0
    ) AS can_view,

    COALESCE(
        permissions.can_edit,
        0
    ) AS can_edit,

    COALESCE(
        permissions.can_approve,
        0
    ) AS can_approve,

    COALESCE(
        permissions.can_delete,
        0
    ) AS can_delete,

    COALESCE(
        permissions.can_share,
        0
    ) AS can_share


FROM users


LEFT JOIN departments

    ON users.department_id = departments.id


LEFT JOIN permissions

    ON users.id = permissions.user_id

";


$params = [];


/*
|--------------------------------------------------------------------------
| MANAGER FILTER
|--------------------------------------------------------------------------
*/

if($isManager){

    /*
     * A manager without a department
     * should not see any users.
     */

    if($departmentId <= 0){

        echo json_encode([

            "success" => true,

            "data" => []

        ]);

        exit;

    }


    $query .= "

        WHERE users.department_id = ?

    ";


    $params[] = $departmentId;

}


/*
|--------------------------------------------------------------------------
| REGULAR USER
|--------------------------------------------------------------------------
|
| Regular users should not have access to
| the permissions-management user list.
|
*/

elseif(!$isAdmin){

    echo json_encode([

        "success" => false,

        "message" => "Access denied."

    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$query .= "

    ORDER BY

        users.first_name ASC,

        users.last_name ASC

";


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

$data = fetchAll(

    $query,

    $params

);


/*
|--------------------------------------------------------------------------
| RETURN JSON
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "data" => $data

]);

?>

