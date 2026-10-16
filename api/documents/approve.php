<?php

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/Permission.php";
require_once __DIR__ . "/../../includes/notifications.php";
require_once __DIR__ . "/../../includes/ApprovalRouter.php";

Auth::protect();

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$user = Auth::getCurrentUser();

if (!$user || empty($user["id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to determine the current user."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate document ID
|--------------------------------------------------------------------------
*/

$id = (int)($_POST["id"] ?? 0);

if ($id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid document ID."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch document
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "
    SELECT *
    FROM documents
    WHERE id = ?
    ",
    [$id]
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
| Approval routing permission
|--------------------------------------------------------------------------
|
| This is the document-specific permission check.
|
*/

if (!ApprovalRouter::canUserApproveDocument(
    (int)$user["id"],
    $id
)) {

    echo json_encode([
        "success" => false,
        "message" => "You do not have permission to approve this document."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent approving an already-approved document
|--------------------------------------------------------------------------
*/

if (
    isset($document["status"]) &&
    strtolower((string)$document["status"]) === "approved"
) {

    echo json_encode([
        "success" => false,
        "message" => "This document has already been approved."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Signed copy must exist in database
|--------------------------------------------------------------------------
*/

if (empty($document["reviewed_file"])) {

    echo json_encode([
        "success" => false,
        "message" => "Please generate the signed copy before approving."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Resolve signed PDF path
|--------------------------------------------------------------------------
|
| reviewed_file normally contains:
|
| uploads/documents/example_signed.pdf
|
| Project root:
|
| C:/xampp/htdocs/Communication/
|
*/

$projectRoot = dirname(__DIR__, 2);

$reviewedFile = str_replace(
    ["/", "\\"],
    DIRECTORY_SEPARATOR,
    (string)$document["reviewed_file"]
);


/*
|--------------------------------------------------------------------------
| Prevent absolute paths
|--------------------------------------------------------------------------
*/

$reviewedFile = ltrim(
    $reviewedFile,
    "\\/"
);


/*
|--------------------------------------------------------------------------
| Build physical path
|--------------------------------------------------------------------------
*/

$reviewedPath =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    $reviewedFile;


/*
|--------------------------------------------------------------------------
| Resolve real paths
|--------------------------------------------------------------------------
*/

$realProjectRoot = realpath($projectRoot);

$realReviewedPath = realpath($reviewedPath);


/*
|--------------------------------------------------------------------------
| Verify project root
|--------------------------------------------------------------------------
*/

if ($realProjectRoot === false) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to determine the application directory."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Signed PDF must physically exist
|--------------------------------------------------------------------------
*/

if ($realReviewedPath === false || !is_file($realReviewedPath)) {

    echo json_encode([
        "success" => false,
        "message" => "The signed document could not be found on the server. Please generate the signed copy again."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent path traversal
|--------------------------------------------------------------------------
*/

$projectPrefix =
    rtrim(
        $realProjectRoot,
        DIRECTORY_SEPARATOR
    ) .
    DIRECTORY_SEPARATOR;


if (
    stripos(
        $realReviewedPath,
        $projectPrefix
    ) !== 0
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid signed document path."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Must be PDF
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        pathinfo(
            $realReviewedPath,
            PATHINFO_EXTENSION
        )
    ) !== "pdf"
) {

    echo json_encode([
        "success" => false,
        "message" => "The signed document is not a PDF."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| File must not be empty
|--------------------------------------------------------------------------
*/

$fileSize = filesize($realReviewedPath);

if ($fileSize === false || $fileSize <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "The signed PDF is empty. Please generate the signed copy again."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify PDF signature
|--------------------------------------------------------------------------
|
| A real PDF normally begins with:
|
| %PDF-
|
*/

$fileHandle = @fopen($realReviewedPath, "rb");

if ($fileHandle === false) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to read the signed PDF."
    ]);

    exit;
}

$pdfHeader = fread($fileHandle, 5);

fclose($fileHandle);


if ($pdfHeader !== "%PDF-") {

    echo json_encode([
        "success" => false,
        "message" => "The signed document is not a valid PDF file."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Approve document
|--------------------------------------------------------------------------
*/

$result = updateData(
    "documents",
    [
        "status"      => "approved",
        "reviewed_by" => (int)$user["id"],
        "reviewed_at" => date("Y-m-d H:i:s")
    ],
    [
        "id" => $id
    ]
);


/*
|--------------------------------------------------------------------------
| Database update failed
|--------------------------------------------------------------------------
*/

if (
    !isset($result["success"]) ||
    !$result["success"]
) {

    echo json_encode([
        "success" => false,
        "message" => "Approval failed.",
        "error"   => $result["error"] ?? null
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Activity log
|--------------------------------------------------------------------------
*/

insertData(
    "activity_logs",
    [
        "user_id"       => (int)$user["id"],
        "activity"      => "Approved " . $document["title"],
        "document_id"   => $id,
        "department_id" => $document["department_id"],
        "activity_type" => "approve"
    ]
);


/*
|--------------------------------------------------------------------------
| Notify document owner
|--------------------------------------------------------------------------
*/

createNotification(
    $document["uploaded_by"],
    "Document Approved",
    "Your document '" .
        $document["title"] .
        "' was reviewed, signed and approved.",
    "approval",
    $id
);


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success"       => true,
    "message"       => "Document approved successfully.",
    "document_id"   => $id,
    "status"        => "approved",
    "reviewed_file" => $document["reviewed_file"]
]);

exit;