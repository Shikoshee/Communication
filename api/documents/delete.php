<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Permission.php";

Auth::protect();

header("Content-Type: application/json; charset=UTF-8");

$user = Auth::getCurrentUser();

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Authentication required."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE PERMISSION
|--------------------------------------------------------------------------
*/

if (!Permission::canDelete()) {

    echo json_encode([
        "success" => false,
        "message" => "You do not have permission to delete documents."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DOCUMENT ID
|--------------------------------------------------------------------------
*/

$documentId = (int)($_POST['id'] ?? 0);

if ($documentId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid document ID."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim($user['role'] ?? 'user')
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
| LOAD DOCUMENT
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do NOT filter is_deleted here.
| We first need to know whether the document exists.
|
*/

$document = fetchRow(
    "
    SELECT *
    FROM documents
    WHERE id = ?
    LIMIT 1
    ",
    [$documentId]
);


if (!$document) {

    echo json_encode([
        "success" => false,
        "message" => "Document ID {$documentId} does not exist."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ALREADY DELETED
|--------------------------------------------------------------------------
*/

if ((int)($document['is_deleted'] ?? 0) === 1) {

    echo json_encode([
        "success" => false,
        "message" => "This document is already in the recycle bin."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| OWNERSHIP
|--------------------------------------------------------------------------
*/

if (
    !$isAdmin &&
    (int)$document['uploaded_by'] !== $userId
) {

    echo json_encode([
        "success" => false,
        "message" => "You can only delete documents that you uploaded."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| MOVE TO RECYCLE BIN
|--------------------------------------------------------------------------
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


if (!$result || !$result['success']) {

    echo json_encode([
        "success" => false,
        "message" =>
            $result['error'] ??
            "Failed to move document to recycle bin."
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
            ($document['title'] ?? 'Untitled Document') .
            "' to recycle bin",

        "document_id" => $documentId,

        "department_id" =>
            $document['department_id'] ?? null,

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

        "title" =>
            "Document Moved to Recycle Bin",

        "message" =>
            "Document '" .
            ($document['title'] ?? 'Untitled Document') .
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
    "message" => "Document moved to the recycle bin successfully."
]);

exit;
