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
 * ==========================================================
 * CURRENT USER
 * ==========================================================
 */

$currentUserId = (int)($user['id'] ?? 0);

$currentRole = strtolower(
    trim(
        (string)($user['role'] ?? '')
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
 * ==========================================================
 * ONLY ADMIN OR MANAGER
 * ==========================================================
 */

if (!$isAdmin && !$isManager) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}


/*
 * ==========================================================
 * TARGET USER
 * ==========================================================
 */

$targetUserId = (int)(
    $_GET['id'] ?? 0
);

if ($targetUserId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid user."
    ]);

    exit;
}


/*
 * ==========================================================
 * GET TARGET USER
 * ==========================================================
 */

$targetUser = fetchRow(

    "SELECT
        id,
        first_name,
        last_name,
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
 * ==========================================================
 * MANAGER DEPARTMENT SECURITY
 * ==========================================================
 */

if ($isManager && !$isAdmin) {

    /*
     * Get manager department from session/user.
     */

    $managerDepartmentId = (int)(
        $user['department_id'] ?? 0
    );


    /*
     * If department isn't available in
     * the authenticated user data, query it.
     */

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


    /*
     * Manager must have a department.
     */

    if ($managerDepartmentId <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Your account is not assigned to a department."
        ]);

        exit;
    }


    /*
     * Target user's department.
     */

    $targetDepartmentId = (int)(
        $targetUser['department_id'] ?? 0
    );


    /*
     * Manager can only access
     * users from their own department.
     */

    if (
        $targetDepartmentId <= 0
        ||
        $targetDepartmentId !== $managerDepartmentId
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Access denied. You can only manage permissions for members of your department."
        ]);

        exit;
    }

}


/*
 * ==========================================================
 * GET PERMISSIONS
 * ==========================================================
 */

$permissions = fetchRow(

    "SELECT

        can_view,
        can_edit,
        can_approve,
        can_delete,
        can_share

     FROM permissions

     WHERE user_id=?

     LIMIT 1",

    [
        $targetUserId
    ]

);


/*
 * ==========================================================
 * NO PERMISSION RECORD YET
 * ==========================================================
 *
 * Return zeros instead of an error.
 * This allows the manager to edit a user
 * who does not have a permissions row yet.
 */

if (!$permissions) {

    $permissions = [

        "can_view" => 0,

        "can_edit" => 0,

        "can_approve" => 0,

        "can_delete" => 0,

        "can_share" => 0

    ];

}


/*
 * ==========================================================
 * RESPONSE
 * ==========================================================
 */

echo json_encode([

    "success" => true,

    "permissions" => [

        "can_view" => (int)(
            $permissions['can_view'] ?? 0
        ),

        "can_edit" => (int)(
            $permissions['can_edit'] ?? 0
        ),

        "can_approve" => (int)(
            $permissions['can_approve'] ?? 0
        ),

        "can_delete" => (int)(
            $permissions['can_delete'] ?? 0
        ),

        "can_share" => (int)(
            $permissions['can_share'] ?? 0
        )

    ]

]);

exit;