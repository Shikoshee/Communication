<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;

}


$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim(
        (string)($user['role'] ?? '')
    )
);


$isAdmin = in_array(
    $userRole,
    [
        'admin',
        'administrator'
    ],
    true
);

$isManager = (
    $userRole === 'manager'
    ||
    str_contains($userRole, 'manager')
);


if (!$isAdmin && !$isManager) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;

}


/*
 * ==========================================================
 * ADMIN
 * ==========================================================
 */

if ($isAdmin) {

    $users = fetchAll("

        SELECT

            id,
            first_name,
            last_name,
            username,
            email,
            department_id

        FROM users

        ORDER BY
            first_name,
            last_name

    ");

}


/*
 * ==========================================================
 * MANAGER
 * ==========================================================
 */

else {

    $departmentId = (int)(
        $user['department_id'] ?? 0
    );


    /*
     * Get department from database if not
     * available in the session.
     */

    if ($departmentId <= 0) {

        $manager = fetchRow("

            SELECT department_id

            FROM users

            WHERE id=?

            LIMIT 1

        ", [
            $userId
        ]);

        $departmentId = (int)(
            $manager['department_id'] ?? 0
        );

    }


    if ($departmentId <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Your account is not assigned to a department."
        ]);

        exit;

    }


    /*
     * Only users in manager's department.
     */

    $users = fetchAll("

        SELECT

            id,
            first_name,
            last_name,
            username,
            email,
            department_id

        FROM users

        WHERE department_id=?

        ORDER BY
            first_name,
            last_name

    ", [
        $departmentId
    ]);

}


echo json_encode([

    "success" => true,

    "data" => $users

]);

exit;
?>
