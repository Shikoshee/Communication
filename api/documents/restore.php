<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Permission.php";


/*
|--------------------------------------------------------------------------
| ALWAYS RETURN JSON
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function restoreResponse($success, $message, $extra = [])
{
    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

try {

    Auth::protect();

} catch (Throwable $e) {

    restoreResponse(
        false,
        "Authentication failed."
    );

}


$user = Auth::getCurrentUser();


if (!$user) {

    restoreResponse(
        false,
        "Authentication required."
    );

}


$userId = (int)($user['id'] ?? 0);


if ($userId <= 0) {

    restoreResponse(
        false,
        "Invalid user account."
    );

}


/*
|--------------------------------------------------------------------------
| PERMISSION
|--------------------------------------------------------------------------
*/

try {

    if (!Permission::canDelete()) {

        restoreResponse(
            false,
            "You do not have permission to restore documents."
        );

    }

} catch (Throwable $e) {

    restoreResponse(
        false,
        "Unable to verify permissions."
    );

}


/*
|--------------------------------------------------------------------------
| DOCUMENT ID
|--------------------------------------------------------------------------
*/

$id = isset($_POST['id'])
    ? (int)$_POST['id']
    : 0;


if ($id <= 0) {

    restoreResponse(
        false,
        "Document ID is missing."
    );

}


/*
|--------------------------------------------------------------------------
| FIND DELETED DOCUMENT
|--------------------------------------------------------------------------
*/

try {

    $document = fetchRow(
        "
        SELECT
            id,
            title,
            uploaded_by,
            department_id,
            is_deleted,
            deleted_by,
            deleted_at
        FROM documents
        WHERE id = ?
        AND is_deleted = 1
        LIMIT 1
        ",
        [$id]
    );

} catch (Throwable $e) {

    error_log(
        "Restore document SELECT error: " .
        $e->getMessage()
    );

    restoreResponse(
        false,
        "Database error while finding the deleted document."
    );

}


if (!$document) {

    restoreResponse(
        false,
        "Deleted document not found."
    );

}


/*
|--------------------------------------------------------------------------
| USER ROLE
|--------------------------------------------------------------------------
*/

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
| RESTORE PERMISSION
|--------------------------------------------------------------------------
|
| Admins can restore any deleted document.
|
| Normal users can restore documents they uploaded.
|
*/

if (
    !$isAdmin &&
    (int)$document['uploaded_by'] !== $userId
) {

    restoreResponse(
        false,
        "You can only restore documents that you uploaded."
    );

}


/*
|--------------------------------------------------------------------------
| RESTORE DOCUMENT
|--------------------------------------------------------------------------
*/

try {

    $result = updateData(
        "documents",
        [
            "is_deleted" => 0,
            "deleted_at" => null,
            "deleted_by" => null
        ],
        [
            "id" => $id
        ]
    );

} catch (Throwable $e) {

    error_log(
        "Restore document UPDATE error: " .
        $e->getMessage()
    );

    restoreResponse(
        false,
        "Database error while restoring the document."
    );

}


/*
|--------------------------------------------------------------------------
| CHECK UPDATE RESULT
|--------------------------------------------------------------------------
|
| updateData() may return an array depending on your config/helper.
| We therefore handle both array and boolean-style responses safely.
|
*/

if (is_array($result)) {

    if (
        isset($result['success']) &&
        $result['success'] === false
    ) {

        restoreResponse(
            false,
            $result['error'] ??
            $result['message'] ??
            "Failed to restore document."
        );

    }

}


/*
|--------------------------------------------------------------------------
| ACTIVITY LOG
|--------------------------------------------------------------------------
*/

try {

    insertData(
        "activity_logs",
        [
            "user_id" => $userId,

            "activity" =>
                "Restored document '" .
                ($document['title'] ?? 'Untitled Document') .
                "'",

            "document_id" =>
                $id,

            "department_id" =>
                $document['department_id'] ?? null,

            "activity_type" =>
                "edit"
        ]
    );

} catch (Throwable $e) {

    /*
     * IMPORTANT:
     *
     * The document has already been restored.
     *
     * Therefore an activity-log failure should NOT make
     * the restore operation appear to have failed.
     *
     * We only record the error in the PHP log.
     */

    error_log(
        "Restore activity log error: " .
        $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

restoreResponse(
    true,
    "Document restored successfully."
);