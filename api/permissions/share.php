<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');


/*
 * ==========================================================
 * CURRENT USER
 * ==========================================================
 */

$user = Auth::getCurrentUser();


if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;

}


$currentUserId = (int)(
    $user['id'] ?? 0
);


$currentRole = strtolower(
    trim(
        (string)($user['role'] ?? '')
    )
);


/*
 * ==========================================================
 * ROLE
 * ==========================================================
 */

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
 * ONLY ADMIN / MANAGER
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
 * GET CURRENT USER DEPARTMENT
 * ==========================================================
 */

$managerDepartmentId = (int)(
    $user['department_id'] ?? 0
);


if ($isManager && $managerDepartmentId <= 0) {

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
 * ==========================================================
 * MANAGER MUST HAVE DEPARTMENT
 * ==========================================================
 */

if ($isManager && $managerDepartmentId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Your account is not assigned to a department."
    ]);

    exit;

}


/*
 * ==========================================================
 * INPUT
 * ==========================================================
 */

$documentId = (int)(
    $_POST['document_id'] ?? 0
);


$users = $_POST['users'] ?? [];


/*
 * Make sure users is an array.
 */

if (!is_array($users)) {

    $users = [
        $users
    ];

}


/*
 * Convert to integers and remove duplicates.
 */

$users = array_values(
    array_unique(
        array_filter(
            array_map(
                'intval',
                $users
            ),
            function ($id) {

                return $id > 0;

            }
        )
    )
);


/*
 * ==========================================================
 * VALIDATE DOCUMENT
 * ==========================================================
 */

if ($documentId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Please select a valid document."
    ]);

    exit;

}


if (empty($users)) {

    echo json_encode([
        "success" => false,
        "message" => "Please select at least one user."
    ]);

    exit;

}


/*
 * ==========================================================
 * GET DOCUMENT
 * ==========================================================
 */

$document = fetchRow(

    "SELECT
        id,
        department_id,
        uploaded_by
     FROM documents
     WHERE id=?
     LIMIT 1",

    [
        $documentId
    ]

);


if (!$document) {

    echo json_encode([
        "success" => false,
        "message" => "Document not found."
    ]);

    exit;

}


/*
 * ==========================================================
 * MANAGER DOCUMENT SECURITY
 * ==========================================================
 *
 * Managers can only share documents belonging
 * to their own department.
 */

if ($isManager && !$isAdmin) {

    $documentDepartmentId = (int)(
        $document['department_id'] ?? 0
    );


    if (
        $documentDepartmentId <= 0
        ||
        $documentDepartmentId !== $managerDepartmentId
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Access denied. You can only share documents belonging to your department."
        ]);

        exit;

    }

}


/*
 * ==========================================================
 * PERMISSION VALUES
 * ==========================================================
 */

$canView = !empty($_POST['can_view'])
    ? 1
    : 0;


$canEdit = !empty($_POST['can_edit'])
    ? 1
    : 0;


$canShare = !empty($_POST['can_share'])
    ? 1
    : 0;


/*
 * ==========================================================
 * PROCESS EACH SELECTED USER
 * ==========================================================
 */

$savedCount = 0;


foreach ($users as $targetUserId) {


    /*
     * ------------------------------------------------------
     * GET TARGET USER
     * ------------------------------------------------------
     */

    $targetUser = fetchRow(

        "SELECT
            id,
            first_name,
            last_name,
            department_id,
            status
         FROM users
         WHERE id=?
         LIMIT 1",

        [
            $targetUserId
        ]

    );


    if (!$targetUser) {

        continue;

    }


    /*
     * ------------------------------------------------------
     * ONLY ACTIVE USERS
     * ------------------------------------------------------
     */

    if (
        isset($targetUser['status'])
        &&
        $targetUser['status'] !== 'active'
    ) {

        continue;

    }


    /*
     * ------------------------------------------------------
     * MANAGER USER SECURITY
     * ------------------------------------------------------
     *
     * A manager can only share with users
     * belonging to their own department.
     */

    if ($isManager && !$isAdmin) {

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
                "message" =>
                    "Access denied. You can only share documents with users in your department."
            ]);

            exit;

        }

    }


    /*
     * ------------------------------------------------------
     * CHECK EXISTING USER PERMISSION
     * ------------------------------------------------------
     */

    $existing = fetchRow(

        "SELECT
            id
         FROM document_permissions
         WHERE document_id=?
         AND user_id=?
         LIMIT 1",

        [
            $documentId,
            $targetUserId
        ]

    );


    /*
     * ------------------------------------------------------
     * UPDATE
     * ------------------------------------------------------
     */

    if ($existing) {

        $result = updateData(

            "document_permissions",

            [

                "can_view" => $canView,

                "can_edit" => $canEdit,

                "can_share" => $canShare

            ],

            [

                "document_id" => $documentId,

                "user_id" => $targetUserId

            ]

        );

    }


    /*
     * ------------------------------------------------------
     * INSERT
     * ------------------------------------------------------
     */

    else {

        $result = insertData(

            "document_permissions",

            [

                "document_id" => $documentId,

                "user_id" => $targetUserId,

                "department_id" => null,

                "can_view" => $canView,

                "can_edit" => $canEdit,

                "can_share" => $canShare

            ]

        );

    }


    /*
     * ------------------------------------------------------
     * DATABASE RESULT
     * ------------------------------------------------------
     */

    if (
        !is_array($result)
        ||
        empty($result['success'])
    ) {

        echo json_encode([

            "success" => false,

            "message" =>
                $result['error']
                ?? "Unable to save permissions for the selected user."

        ]);

        exit;

    }


    $savedCount++;

}


/*
 * ==========================================================
 * NOTHING SAVED
 * ==========================================================
 */

if ($savedCount <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "No permissions were saved."
    ]);

    exit;

}


/*
 * ==========================================================
 * SUCCESS
 * ==========================================================
 */

echo json_encode([

    "success" => true,

    "message" =>
        $savedCount === 1
            ? "Document permission saved successfully."
            : $savedCount . " users were granted access successfully."

]);

exit;
