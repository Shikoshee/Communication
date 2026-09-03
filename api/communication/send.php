<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/notifications.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


// ==========================================================
// GET FORM DATA
// ==========================================================

$conversationId = isset($_POST['conversation_id'])
    ? (int)$_POST['conversation_id']
    : 0;

$message = trim(
    $_POST['message'] ?? ""
);

$documentId = !empty($_POST['document_id'])
    ? (int)$_POST['document_id']
    : null;


// ==========================================================
// IMAGE UPLOAD
// ==========================================================

$imagePath = null;

if (
    isset($_FILES['image']) &&
    $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

        echo json_encode([
            "success" => false,
            "message" => "Image upload failed."
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | Maximum image size
    |----------------------------------------------------------------------
    */

    $maxSize = 5 * 1024 * 1024; // 5 MB

    if ($_FILES['image']['size'] > $maxSize) {

        echo json_encode([
            "success" => false,
            "message" => "Image must not exceed 5 MB."
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | Validate MIME type
    |----------------------------------------------------------------------
    */

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];


    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file(
        $_FILES['image']['tmp_name']
    );


    if (!isset($allowedTypes[$mimeType])) {

        echo json_encode([
            "success" => false,
            "message" => "Only JPG, PNG, GIF and WEBP images are allowed."
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | Upload directory
    |----------------------------------------------------------------------
    */

    $uploadDirectory =
        "../../uploads/communication/images/";


    if (!is_dir($uploadDirectory)) {

        mkdir(
            $uploadDirectory,
            0755,
            true
        );
    }


    /*
    |----------------------------------------------------------------------
    | Generate safe unique filename
    |----------------------------------------------------------------------
    */

    $extension =
        $allowedTypes[$mimeType];

    $fileName =
        uniqid("chat_", true) .
        "." .
        $extension;


    $destination =
        $uploadDirectory .
        $fileName;


    /*
    |----------------------------------------------------------------------
    | Move uploaded image
    |----------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $_FILES['image']['tmp_name'],
            $destination
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Unable to save image."
        ]);

        exit;
    }


    /*
    |----------------------------------------------------------------------
    | Path stored in database
    |----------------------------------------------------------------------
    */

    $imagePath =
        "uploads/communication/images/" .
        $fileName;

}

// ==========================================================
// VALIDATE CONVERSATION
// ==========================================================

if (!$conversationId) {

    echo json_encode([
        "success" => false,
        "message" => "Conversation not found."
    ]);

    exit;
}


// ==========================================================
// VALIDATE MESSAGE
// ==========================================================

if (
    empty($message) &&
    empty($documentId)
) {

    echo json_encode([
        "success" => false,
        "message" => "Nothing to send."
    ]);

    exit;
}


// ==========================================================
// VALIDATE DOCUMENT
// ==========================================================

$document = null;

if ($documentId) {

    $document = fetchRow(

        "
        SELECT
            id,
            title

        FROM documents

        WHERE id=?

        LIMIT 1
        ",

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

}


// ==========================================================
// GET CONVERSATION
// ==========================================================

$conversation = fetchRow(

    "
    SELECT
        id,
        user_one,
        user_two

    FROM conversations

    WHERE id=?

    LIMIT 1
    ",

    [
        $conversationId
    ]

);


if (!$conversation) {

    echo json_encode([
        "success" => false,
        "message" => "Conversation not found."
    ]);

    exit;
}


// ==========================================================
// DETERMINE RECEIVER
// ==========================================================

$currentUserId = (int)$user['id'];

$userOne = (int)$conversation['user_one'];
$userTwo = (int)$conversation['user_two'];


if ($userOne === $currentUserId) {

    $receiver = $userTwo;

} elseif ($userTwo === $currentUserId) {

    $receiver = $userOne;

} else {

    echo json_encode([
        "success" => false,
        "message" => "You are not a member of this conversation."
    ]);

    exit;
}


// ==========================================================
// SAVE MESSAGE
// ==========================================================

$result = insertData(

    "messages",

    [

        "conversation_id" =>
            $conversationId,

        "sender_id" =>
            $currentUserId,

        "message" =>
            $message,

        "document_id" =>
            $documentId

    ]

);


if (!$result['success']) {

    echo json_encode([

        "success" => false,

        "message" =>
            $result['error']
            ?? "Failed to send message."

    ]);

    exit;
}


// ==========================================================
// SENDER NAME
// ==========================================================

$senderName = trim(

    ($user['first_name'] ?? '') .
    ' ' .
    ($user['last_name'] ?? '')

);


if ($senderName === '') {

    $senderName = "Someone";

}


// ==========================================================
// MESSAGE NOTIFICATION
// ==========================================================

if (!empty($message)) {

    createNotification(

        $receiver,

        "New Message",

        $senderName .
        " sent: " .
        mb_strimwidth(
            $message,
            0,
            80,
            "..."
        ),

        "message",

        null,

        $conversationId

    );

}


// ==========================================================
// DOCUMENT NOTIFICATION
// ==========================================================

if ($documentId && $document) {

    createNotification(

        $receiver,

        "Document Shared",

        $senderName .
        " shared '" .
        $document['title'] .
        "'.",

        "sharing",

        $documentId,

        $conversationId

    );

}


// ==========================================================
// RESPONSE
// ==========================================================

echo json_encode([

    "success" => true,

    "message" =>
        "Message sent successfully.",

    "message_id" =>
        $result['insert_id'] ?? null

]);

exit;
