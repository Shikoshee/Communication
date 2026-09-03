<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Permission.php";

Auth::protect();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK DELETE PERMISSION
|--------------------------------------------------------------------------
*/

if (!Permission::canDelete()) {

    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to delete documents.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE DOCUMENT ID
|--------------------------------------------------------------------------
*/

if (!isset($_POST['id']) || (int)$_POST['id'] <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Document ID missing.'
    ]);

    exit;
}

$documentId = (int)$_POST['id'];


/*
|--------------------------------------------------------------------------
| GET DOCUMENT
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "
    SELECT *
    FROM documents
    WHERE id=?
    AND is_deleted=0
    LIMIT 1
    ",
    [$documentId]
);


if (!$document) {

    echo json_encode([
        'success' => false,
        'message' => 'Document not found or already in the recycle bin.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK OWNERSHIP / ADMIN
|--------------------------------------------------------------------------
|
| Delete permission must still respect ownership unless the user
| is an administrator.
|
*/

$userId = (int)$user['id'];

$userRole = strtolower(
    trim(
        $user['role'] ?? 'user'
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


/*
|--------------------------------------------------------------------------
| NORMAL USERS CAN DELETE ONLY THEIR OWN DOCUMENTS
|--------------------------------------------------------------------------
*/

if (
    !$isAdmin &&
    (int)$document['uploaded_by'] !== $userId
) {

    echo json_encode([
        'success' => false,
        'message' => 'You can only delete documents that you uploaded.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| MOVE DOCUMENT TO RECYCLE BIN
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We DO NOT delete the physical file.
|
| We DO NOT delete the database record.
|
*/

$result = updateData(

    "documents",

    [

        "is_deleted" => 1,

        "deleted_at" => date("Y-m-d H:i:s"),

        "deleted_by" => $userId

    ],

    [

        "id" => $documentId

    ]

);


if (!$result['success']) {

    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Failed to move document to recycle bin.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTIVITY LOG
|--------------------------------------------------------------------------
*/

insertData(

    "activity_logs",

    [

        "user_id" => $userId,

        "activity" =>
            "Moved document '" .
            $document['title'] .
            "' to recycle bin",

        "document_id" => $documentId,

        "department_id" =>
            $document['department_id'],

        "activity_type" => "delete"

    ]

);


/*
|--------------------------------------------------------------------------
| NOTIFICATION
|--------------------------------------------------------------------------
*/

insertData(

    "notifications",

    [

        "user_id" => $userId,

        "title" => "Document Moved to Recycle Bin",

        "message" =>
            "Document '" .
            $document['title'] .
            "' was moved to the recycle bin.",

        "type" => "system",

        "related_document_id" => $documentId

    ]

);


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "Document moved to the recycle bin successfully."

]);

exit;

