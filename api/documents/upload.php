<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/notifications.php";

Auth::protect();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();


// ==========================================================
// VALIDATE FILE
// ==========================================================

if (
    !isset($_FILES['document']) ||
    $_FILES['document']['error'] !== UPLOAD_ERR_OK
) {

    echo json_encode([
        "success" => false,
        "message" => "No valid file uploaded."
    ]);

    exit;
}


// ==========================================================
// GET FORM DATA
// ==========================================================

$title = trim($_POST['title'] ?? '');

$description = trim(
    $_POST['description'] ?? ''
);

// ==========================================================
// SELECTED DEPARTMENTS
// ==========================================================

$departments = $_POST['department_id'] ?? [];

// Make sure it is always an array.

if (!is_array($departments)) {

    $departments = [$departments];

}

// Convert IDs to integers and remove duplicates.

$departments = array_values(
    array_unique(
        array_filter(
            array_map('intval', $departments),
            function ($id) {
                return $id > 0;
            }
        )
    )
);


// ==========================================================
// PRIMARY DEPARTMENT
// ==========================================================
//
// Your existing documents table still has department_id.
// We keep the first selected department as the primary
// department so existing functionality continues working.
//

$department = $departments[0] ?? 0;


$tags = trim(
    $_POST['tags'] ?? ''
);


$approval = isset($_POST['approval']) && $_POST['approval'] === 'on';

$status = $approval ? 'pending' : 'approved';


// ==========================================================
// VALIDATE REQUIRED DATA
// ==========================================================

if ($title === '') {

    echo json_encode([
        "success" => false,
        "message" => "Document title is required."
    ]);

    exit;
}


if ($department <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Please select a department."
    ]);

    exit;
}


// ==========================================================
// SELECTED USERS FOR SHARING
// ==========================================================
//
// upload.php sends:
//
// shareUsers[]
//
// Because upload.js uses FormData(this), these values
// automatically arrive here.
//

$shareUsers = $_POST['shareUsers'] ?? [];


// Make sure it is always an array.

if (!is_array($shareUsers)) {

    $shareUsers = [$shareUsers];

}


// Convert IDs to integers and remove duplicates.

$shareUsers = array_values(
    array_unique(
        array_filter(
            array_map('intval', $shareUsers),
            function ($id) {
                return $id > 0;
            }
        )
    )
);

// ==========================================================
// ADD ALL USERS FROM SELECTED DEPARTMENTS
// ==========================================================

$departmentUsers = [];

if (!empty($departments)) {

    // Create ?,?,? placeholders for the IN clause.

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($departments),
            '?'
        )
    );


    $departmentUsers = fetchAll(

        "SELECT id
         FROM users
         WHERE department_id IN ($placeholders)
         AND status='active'",

        $departments

    );

}


// Add department members to share list.

foreach ($departmentUsers as $departmentUser) {

    $shareUsers[] =
        (int)$departmentUser['id'];

}


// Remove duplicates.

$shareUsers = array_values(
    array_unique(
        array_map(
            'intval',
            $shareUsers
        )
    )
);


// ==========================================================
// FILE
// ==========================================================

$file = $_FILES['document'];


// Use a safer unique filename.

$originalName = basename(
    $file['name']
);

$filename =
    time() .
    "_" .
    uniqid() .
    "_" .
    $originalName;


$uploadPath =
    "../../uploads/documents/" .
    $filename;


// ==========================================================
// MOVE FILE
// ==========================================================

if (!move_uploaded_file(
    $file['tmp_name'],
    $uploadPath
)) {

    echo json_encode([
        "success" => false,
        "message" => "Failed to save uploaded file."
    ]);

    exit;
}


// ==========================================================
// DOCUMENT STATUS
// ==========================================================

$status = $approval
    ? "pending"
    : "approved";


// ==========================================================
// CREATE DOCUMENT
// ==========================================================

$result = insertData(

    "documents",

    [

        "title" => $title,

        "description" => $description,

        "file_name" => $filename,

        "file_path" =>
            "uploads/documents/" .
            $filename,

        "file_size" => (int)$file['size'],

        "file_type" =>
            $file['type'] ?? '',

        "department_id" =>
            $department,

        "uploaded_by" =>
            (int)$user['id'],

        "version" => "1.0",

        "status" => $status,

        "tags" => $tags

    ]

);


if (!$result['success']) {

    // Remove uploaded file if database insertion failed.

    if (file_exists($uploadPath)) {

        unlink($uploadPath);

    }


    echo json_encode([

        "success" => false,

        "message" =>
            $result['error']
            ?? "Failed to save document."

    ]);

    exit;
}


$documentId =
    (int)$result['insert_id'];


// ==========================================================
// ACTIVITY LOG
// ==========================================================

insertData(

    "activity_logs",

    [

        "user_id" =>
            (int)$user['id'],

        "activity" =>
            "Uploaded document " . $title,

        "document_id" =>
            $documentId,

        "department_id" =>
            $department,

        "activity_type" =>
            "upload"

    ]

);


// ==========================================================
// APPROVAL NOTIFICATIONS
// ==========================================================

if ($approval) {

    $approvers = fetchAll("

        SELECT id

        FROM users

        WHERE role IN ('admin','manager')

        AND status='active'

    ");


    foreach ($approvers as $approver) {

        createNotification(

            (int)$approver['id'],

            "Document Approval Required",

            $user['first_name'] .
            " uploaded " .
            $title .
            " and requires approval.",

            "approval",

            $documentId

        );

    }

}


// ==========================================================
// SHARE DOCUMENT WITH SELECTED USERS
// ==========================================================

$sharedCount = 0;

$shareErrors = [];


// Sender name.

$senderName = trim(

    ($user['first_name'] ?? '') .
    ' ' .
    ($user['last_name'] ?? '')

);


if ($senderName === '') {

    $senderName = 'A user';

}


// Process every selected user.

foreach ($shareUsers as $recipientId) {


    // ------------------------------------------------------
    // Do not share with yourself.
    // ------------------------------------------------------

    if ($recipientId === (int)$user['id']) {

        $shareErrors[] =
            "You cannot share a document with yourself.";

        continue;

    }


    // ------------------------------------------------------
    // Make sure recipient exists and is active.
    // ------------------------------------------------------

    $recipient = fetchRow(

        "SELECT
            id,
            first_name,
            last_name,
            email
         FROM users
         WHERE id=?
         AND status='active'
         LIMIT 1",

        [$recipientId]

    );


    if (!$recipient) {

        $shareErrors[] =
            "One selected user was not found or is inactive.";

        continue;

    }


    // ------------------------------------------------------
    // Prevent duplicate share.
    // ------------------------------------------------------

    $existingShare = fetchRow(

        "SELECT id
         FROM document_shares
         WHERE document_id=?
         AND user_id=?
         LIMIT 1",

        [

            $documentId,

            $recipientId

        ]

    );


    if ($existingShare) {

        continue;

    }


    // ------------------------------------------------------
    // CREATE SHARE RECORD
    // ------------------------------------------------------

    $shareResult = insertData(

        "document_shares",

        [

            "document_id" =>
                $documentId,

            "shared_by" =>
                (int)$user['id'],

            "user_id" =>
                $recipientId

        ]

    );


    if (!$shareResult['success']) {

        $shareErrors[] =
            "Failed to share with " .
            trim(
                ($recipient['first_name'] ?? '') .
                ' ' .
                ($recipient['last_name'] ?? '')
            ) .
            ".";

        continue;

    }


    $sharedCount++;


    // ------------------------------------------------------
    // NOTIFY RECIPIENT
    // ------------------------------------------------------

    createNotification(

        $recipientId,

        "Document Shared",

        $senderName .
        ' shared "' .
        $title .
        '" with you.',

        "sharing",

        $documentId

    );

}


// ==========================================================
// RESPONSE MESSAGE
// ==========================================================

$message =
    "Document uploaded successfully.";


if ($sharedCount > 0) {

    $message .=
        " Shared with " .
        $sharedCount .
        " user" .
        ($sharedCount === 1 ? "" : "s") .
        ".";

}


if (!empty($shareErrors)) {

    $message .=
        " " .
        implode(" ", $shareErrors);

}


// ==========================================================
// FINAL RESPONSE
// ==========================================================

echo json_encode([

    "success" => true,

    "message" => $message,

    "document_id" => $documentId,

    "shared_count" => $sharedCount

]);

exit;

