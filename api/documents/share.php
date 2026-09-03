<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/Permission.php';

Auth::protect();

header('Content-Type: application/json');

if (!Permission::canShare()) {

    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to share documents.'
    ]);

    exit;
} 




// ==========================================================
// VALIDATE INPUT
// ==========================================================

if (
    !isset($_POST['document_id']) ||
    !isset($_POST['user_id'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Required data missing'
    ]);

    exit;
}


$documentId = (int)$_POST['document_id'];
$userId     = (int)$_POST['user_id'];

$permission = $_POST['permission'] ?? 'read';


// ==========================================================
// CURRENT USER
// ==========================================================

$currentUser = Auth::getCurrentUser();

$sharedBy = (int)($currentUser['id'] ?? 0);


if ($sharedBy <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to identify the current user.'
    ]);

    exit;
}


// ==========================================================
// VALIDATE RECIPIENT
// ==========================================================

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

    [$userId]

);


if (!$recipient) {

    echo json_encode([
        'success' => false,
        'message' => 'Selected user was not found or is inactive.'
    ]);

    exit;
}


// ==========================================================
// VALIDATE DOCUMENT
// ==========================================================

$document = fetchRow(

    "SELECT
        id,
        title
     FROM documents
     WHERE id=?
     LIMIT 1",

    [$documentId]

);


if (!$document) {

    echo json_encode([
        'success' => false,
        'message' => 'Document not found.'
    ]);

    exit;
}


// ==========================================================
// PREVENT SHARING TO YOURSELF
// ==========================================================

if ($sharedBy === $userId) {

    echo json_encode([
        'success' => false,
        'message' => 'You cannot share a document with yourself.'
    ]);

    exit;
}


// ==========================================================
// CHECK EXISTING SHARE
// ==========================================================

$existingShare = fetchRow(

    "SELECT id
     FROM document_shares
     WHERE document_id=?
     AND user_id=?
     LIMIT 1",

    [
        $documentId,
        $userId
    ]

);


if ($existingShare) {

    echo json_encode([
        'success' => false,
        'message' => 'This document has already been shared with this user.'
    ]);

    exit;
}


// ==========================================================
// CREATE SHARE
// ==========================================================

$shareResult = insertData(

    "document_shares",

    [

        "document_id" => $documentId,

        "shared_by" => $sharedBy,

        "user_id" => $userId

    ]

);


if (!$shareResult['success']) {

    echo json_encode([

        'success' => false,

        'message' =>
            $shareResult['error']
            ?? 'Failed to share document.'

    ]);

    exit;
}


// ==========================================================
// GET SENDER NAME
// ==========================================================

$senderName = trim(

    ($currentUser['first_name'] ?? '') .
    ' ' .
    ($currentUser['last_name'] ?? '')

);


if ($senderName === '') {

    $senderName = 'A user';

}


// ==========================================================
// CREATE NOTIFICATION FOR RECIPIENT
// ==========================================================

$notificationResult = insertData(

    "notifications",

    [

        "user_id" => $userId,

        "title" => "Document Shared",

        "message" =>
            $senderName .
            ' shared "' .
            $document['title'] .
            '" with you.',

        "type" => "sharing",

        "related_document_id" => $documentId

    ]

);


// ==========================================================
// SUCCESS
// ==========================================================

echo json_encode([

    'success' => true,

    'message' =>
        'Document shared successfully with ' .
        trim(
            ($recipient['first_name'] ?? '') .
            ' ' .
            ($recipient['last_name'] ?? '')
        ),

    'shared_by' => $senderName,

    'shared_with' =>
        trim(
            ($recipient['first_name'] ?? '') .
            ' ' .
            ($recipient['last_name'] ?? '')
        )

]);

exit;
