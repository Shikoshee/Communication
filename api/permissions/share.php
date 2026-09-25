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
| DOCUMENT
|--------------------------------------------------------------------------
*/

$documentId = (int)(
    $_POST['document_id'] ?? 0
);


if ($documentId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid document."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

$users = $_POST['users'] ?? [];


if (!is_array($users)) {

    $users = [
        $users
    ];

}


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


if (empty($users)) {

    echo json_encode([
        "success" => false,
        "message" => "Please select at least one user."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET DOCUMENT
|--------------------------------------------------------------------------
*/

$document = fetchRow(

    "SELECT
        id,
        uploaded_by,
        department_id
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
|--------------------------------------------------------------------------
| SHARE AUTHORIZATION
|--------------------------------------------------------------------------
|
| Admin:
|     Can share any document.
|
| Manager:
|     Can share documents belonging
|     to their own department.
|
| Regular user:
|     Must have can_share permission.
|
*/

$allowedToShare = false;


if ($isAdmin) {

    $allowedToShare = true;

}


elseif ($isManager) {

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


    if (
        $managerDepartmentId > 0
        &&
        (int)$document['department_id']
        ===
        $managerDepartmentId
    ) {

        $allowedToShare = true;

    }

}


else {

    $permission = fetchRow(

        "SELECT can_share
         FROM permissions
         WHERE user_id=?
         LIMIT 1",

        [
            $currentUserId
        ]

    );


    if (
        $permission
        &&
        !empty($permission['can_share'])
    ) {

        $allowedToShare = true;

    }

}


if (!$allowedToShare) {

    echo json_encode([
        "success" => false,
        "message" => "You do not have permission to share this document."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| SHARE WITH USERS
|--------------------------------------------------------------------------
*/

$sharedCount = 0;


foreach ($users as $targetUserId) {

    /*
     * Do not create a share record
     * for the person sharing it.
     */

    if ($targetUserId === $currentUserId) {
        continue;
    }


    /*
     * Make sure target user exists
     * and is active.
     */

    $targetUser = fetchRow(

        "SELECT id
         FROM users
         WHERE id=?
         AND status='active'
         LIMIT 1",

        [
            $targetUserId
        ]

    );


    if (!$targetUser) {
        continue;
    }


    /*
     * Check existing share.
     */

    $existing = fetchRow(

        "SELECT id
         FROM document_shares
         WHERE document_id=?
         AND user_id=?
         LIMIT 1",

        [
            $documentId,
            $targetUserId
        ]

    );


    /*
     * Already shared.
     */

    if ($existing) {

        /*
         * Update who shared it most recently.
         */

        updateData(

            "document_shares",

            [
                "shared_by" => $currentUserId
            ],

            [
                "id" => (int)$existing['id']
            ]

        );

        $sharedCount++;

        continue;
    }


    /*
     * Create new share.
     */

    $result = insertData(

        "document_shares",

        [

            "document_id" => $documentId,

            "user_id" => $targetUserId,

            "shared_by" => $currentUserId

        ]

    );


    if (
        is_array($result)
        &&
        !empty($result['success'])
    ) {

        $sharedCount++;

    }

}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

if ($sharedCount <= 0) {

    echo json_encode([

        "success" => false,

        "message" => "No document shares were created."

    ]);

    exit;
}


echo json_encode([

    "success" => true,

    "message" =>
        "Document shared with "
        . $sharedCount
        . " user"
        . ($sharedCount === 1 ? "" : "s")
        . " successfully."

]);

exit;