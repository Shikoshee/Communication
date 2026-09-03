<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = (int)($user['id'] ?? 0);

$currentRole = strtolower(
    trim(
        (string)($user['role'] ?? 'user')
    )
);


$isAdmin = in_array(
    $currentRole,
    [
        'admin',
        'administrator'
    ],
    true
);


$isManager = (
    $currentRole === 'manager'
    ||
    str_contains($currentRole, 'manager')
);


/*
|--------------------------------------------------------------------------
| ONLY ADMIN OR MANAGER CAN MANAGE PERMISSIONS
|--------------------------------------------------------------------------
*/

if (!$isAdmin && !$isManager) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| TARGET USER
|--------------------------------------------------------------------------
*/

$targetUserId = (int)(
    $_POST['user_id'] ?? 0
);


if ($targetUserId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid user."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| TARGET USER
|--------------------------------------------------------------------------
*/

$targetUser = fetchRow(

    "SELECT
        id,
        department_id,
        role
     FROM users
     WHERE id=?
     LIMIT 1",

    [
        $targetUserId
    ]

);


if (!$targetUser) {

    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| MANAGER SECURITY
|--------------------------------------------------------------------------
*/

if ($isManager && !$isAdmin) {

    $managerDepartmentId = (int)(
        $user['department_id'] ?? 0
    );


    if ($managerDepartmentId <= 0) {

        $manager = fetchRow(

            "SELECT department_id
             FROM users
             WHERE id=?
             LIMIT 1",

            [
                $currentUserId
            ]

        );

        $managerDepartmentId = (int)(
            $manager['department_id'] ?? 0
        );

    }


    if ($managerDepartmentId <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Your account is not assigned to a department."
        ]);

        exit;
    }


    $targetDepartmentId = (int)(
        $targetUser['department_id'] ?? 0
    );


    if (
        $targetDepartmentId <= 0
        ||
        $targetDepartmentId !== $managerDepartmentId
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Access denied. You can only manage users in your department."
        ]);

        exit;
    }

}


/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/

$canView = !empty($_POST['can_view']) ? 1 : 0;

$canEdit = !empty($_POST['can_edit']) ? 1 : 0;

$canApprove = !empty($_POST['can_approve']) ? 1 : 0;

$canDelete = !empty($_POST['can_delete']) ? 1 : 0;

$canShare = !empty($_POST['can_share']) ? 1 : 0;


/*
|--------------------------------------------------------------------------
| CHECK EXISTING RECORD
|--------------------------------------------------------------------------
*/

$existing = fetchRow(

    "SELECT id
     FROM permissions
     WHERE user_id=?
     LIMIT 1",

    [
        $targetUserId
    ]

);


/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

if ($existing) {

    $result = updateData(

        "permissions",

        [

            "can_view" => $canView,

            "can_edit" => $canEdit,

            "can_approve" => $canApprove,

            "can_delete" => $canDelete,

            "can_share" => $canShare

        ],

        [

            "user_id" => $targetUserId

        ]

    );

}


/*
|--------------------------------------------------------------------------
| INSERT
|--------------------------------------------------------------------------
*/

else {

    $result = insertData(

        "permissions",

        [

            "user_id" => $targetUserId,

            "can_view" => $canView,

            "can_edit" => $canEdit,

            "can_approve" => $canApprove,

            "can_delete" => $canDelete,

            "can_share" => $canShare

        ]

    );

}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

if (
    is_array($result)
    &&
    !empty($result['success'])
) {

    echo json_encode([

        "success" => true,

        "message" => "Permissions updated successfully."

    ]);

    exit;
}


echo json_encode([

    "success" => false,

    "message" => (
        !empty($result['error'])
            ? $result['error']
            : "Unable to update permissions."
    )

]);

exit;
