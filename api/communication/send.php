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

$conversationId = isset($_POST["conversation_id"])
    ? (int) $_POST["conversation_id"]
    : 0;

$message = trim($_POST["message"] ?? "");

$documentId = !empty($_POST["document_id"])
    ? (int) $_POST["document_id"]
    : null;


// ==========================================================
// DEBUG - REMOVE LATER
// ==========================================================

/*
 * Temporarily log what PHP receives.
 *
 * Check:
 * /Communication/debug_upload.txt
 */

$debug = [
    "POST" => $_POST,
    "FILES" => $_FILES,
];

file_put_contents(
    dirname(__DIR__, 2) . "/debug_upload.txt",
    print_r($debug, true)
);


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
    [$conversationId]
);


if (!$conversation) {

    echo json_encode([
        "success" => false,
        "message" => "Conversation not found."
    ]);

    exit;
}


$currentUserId =
    (int) $user["id"];

$userOne =
    (int) $conversation["user_one"];

$userTwo =
    (int) $conversation["user_two"];


if ($userOne === $currentUserId) {

    $receiver =
        $userTwo;

} elseif ($userTwo === $currentUserId) {

    $receiver =
        $userOne;

} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "You are not a member of this conversation."
    ]);

    exit;
}


// ==========================================================
// IMAGE UPLOAD
// ==========================================================

$imagePath = null;

$uploadedImageDestination = null;


if (isset($_FILES["image"])) {

    $file = $_FILES["image"];


    // ------------------------------------------------------
    // UPLOAD ERROR
    // ------------------------------------------------------

    $uploadError =
        (int) ($file["error"] ?? UPLOAD_ERR_NO_FILE);


    if ($uploadError !== UPLOAD_ERR_OK) {

        $errors = [

            UPLOAD_ERR_INI_SIZE =>
                "The image is larger than the server upload limit.",

            UPLOAD_ERR_FORM_SIZE =>
                "The image is larger than the allowed form limit.",

            UPLOAD_ERR_PARTIAL =>
                "The image upload was interrupted.",

            UPLOAD_ERR_NO_FILE =>
                "No image file was received.",

            UPLOAD_ERR_NO_TMP_DIR =>
                "The server temporary upload directory is missing.",

            UPLOAD_ERR_CANT_WRITE =>
                "The server could not write the uploaded image.",

            UPLOAD_ERR_EXTENSION =>
                "A PHP extension stopped the image upload."
        ];


        echo json_encode([
            "success" => false,
            "message" =>
                $errors[$uploadError]
                ??
                "Image upload failed. Error code: " .
                $uploadError
        ]);

        exit;
    }


    // ------------------------------------------------------
    // TEMP FILE
    // ------------------------------------------------------

    if (
        empty($file["tmp_name"]) ||
        !is_uploaded_file(
            $file["tmp_name"]
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "PHP did not receive a valid uploaded image."
        ]);

        exit;
    }


    // ------------------------------------------------------
    // SIZE
    // ------------------------------------------------------

    $maxSize =
        5 * 1024 * 1024;


    if (
        (int) $file["size"] >
        $maxSize
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Image must not exceed 5 MB."
        ]);

        exit;
    }


    // ------------------------------------------------------
    // MIME TYPE
    // ------------------------------------------------------

    $allowedTypes = [

        "image/jpeg" =>
            "jpg",

        "image/png" =>
            "png",

        "image/gif" =>
            "gif",

        "image/webp" =>
            "webp"
    ];


    if (!class_exists("finfo")) {

        echo json_encode([
            "success" => false,
            "message" =>
                "PHP fileinfo extension is not available."
        ]);

        exit;
    }


    $finfo =
        new finfo(FILEINFO_MIME_TYPE);


    $mimeType =
        $finfo->file(
            $file["tmp_name"]
        );


    if (
        !isset(
            $allowedTypes[$mimeType]
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Only JPG, PNG, GIF and WEBP images are allowed."
        ]);

        exit;
    }


    // ------------------------------------------------------
    // UPLOAD DIRECTORY
    // ------------------------------------------------------

    $uploadDirectory =
        dirname(__DIR__, 2) .
        "/uploads/communication/images/";


    if (!is_dir($uploadDirectory)) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
        ) {

            echo json_encode([
                "success" => false,
                "message" =>
                    "Unable to create image upload directory."
            ]);

            exit;
        }
    }


    if (!is_writable($uploadDirectory)) {

        echo json_encode([
            "success" => false,
            "message" =>
                "The image upload directory is not writable."
        ]);

        exit;
    }


    // ------------------------------------------------------
    // FILE NAME
    // ------------------------------------------------------

    $extension =
        $allowedTypes[$mimeType];


    $fileName =
        "chat_" .
        bin2hex(
            random_bytes(16)
        ) .
        "." .
        $extension;


    $destination =
        $uploadDirectory .
        $fileName;


    // ------------------------------------------------------
    // MOVE IMAGE
    // ------------------------------------------------------

    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $destination
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Unable to save image to the server."
        ]);

        exit;
    }


    $uploadedImageDestination =
        $destination;


    // ------------------------------------------------------
    // DATABASE PATH
    // ------------------------------------------------------

    $imagePath =
        "uploads/communication/images/" .
        $fileName;
}


// ==========================================================
// VALIDATE MESSAGE CONTENT
// ==========================================================

if (
    $message === "" &&
    empty($documentId) &&
    empty($imagePath)
) {

    if (
        $uploadedImageDestination &&
        file_exists(
            $uploadedImageDestination
        )
    ) {

        unlink(
            $uploadedImageDestination
        );
    }


    echo json_encode([
        "success" => false,
        "message" =>
            "Nothing to send."
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
        [$documentId]
    );


    if (!$document) {

        if (
            $uploadedImageDestination &&
            file_exists(
                $uploadedImageDestination
            )
        ) {

            unlink(
                $uploadedImageDestination
            );
        }


        echo json_encode([
            "success" => false,
            "message" =>
                "Document not found."
        ]);

        exit;
    }
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
            $documentId,

        "image_path" =>
            $imagePath
    ]
);


if (
    !$result ||
    empty($result["success"])
) {

    if (
        $uploadedImageDestination &&
        file_exists(
            $uploadedImageDestination
        )
    ) {

        unlink(
            $uploadedImageDestination
        );
    }


    echo json_encode([
        "success" => false,
        "message" =>
            $result["error"]
            ??
            "Failed to save message."
    ]);

    exit;
}


// ==========================================================
// SENDER NAME
// ==========================================================

$senderName =
    trim(
        ($user["first_name"] ?? "") .
        " " .
        ($user["last_name"] ?? "")
    );


if ($senderName === "") {

    $senderName =
        "Someone";
}


// ==========================================================
// TEXT NOTIFICATION
// ==========================================================

if ($message !== "") {

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

if (
    $documentId &&
    $document
) {

    createNotification(
        $receiver,
        "Document Shared",
        $senderName .
        " shared '" .
        $document["title"] .
        "'.",
        "sharing",
        $documentId,
        $conversationId
    );
}


// ==========================================================
// IMAGE NOTIFICATION
// ==========================================================

if ($imagePath) {

    createNotification(
        $receiver,
        "New Image",
        $senderName .
        " sent you an image.",
        "message",
        null,
        $conversationId
    );
}


// ==========================================================
// RESPONSE
// ==========================================================

echo json_encode([

    "success" =>
        true,

    "message" =>
        "Message sent successfully.",

    "message_id" =>
        $result["insert_id"]
        ?? null,

    "image_path" =>
        $imagePath

]);

exit;
