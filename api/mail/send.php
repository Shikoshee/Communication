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


$senderId = (int)($user['id'] ?? 0);

$recipientId = (int)(
    $_POST['to'] ?? 0
);

$subject = trim(
    (string)(
        $_POST['subject'] ?? ''
    )
);

$body = trim(
    (string)(
        $_POST['body'] ?? ''
    )
);

$draftId = (int)(
    $_POST['draft_id'] ?? 0
);

$parentId = (int)(
    $_POST['parent_id'] ?? 0
);

$messageType = (string)(
    $_POST['message_type'] ?? 'sent'
);


/*
 * ==========================================================
 * VALID MESSAGE TYPES
 * ==========================================================
 */

$allowedTypes = [
    'sent',
    'reply',
    'forward'
];

if (!in_array($messageType, $allowedTypes, true)) {

    $messageType = 'sent';

}


/*
 * ==========================================================
 * VALIDATE RECIPIENT
 * ==========================================================
 */

if ($recipientId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Please select a recipient."
    ]);

    exit;
}


/*
 * ==========================================================
 * VALIDATE BODY
 * ==========================================================
 */

if ($body === '') {

    echo json_encode([
        "success" => false,
        "message" => "Message body cannot be empty."
    ]);

    exit;
}


/*
 * ==========================================================
 * CHECK RECIPIENT
 * ==========================================================
 */

$recipient = fetchRow(

    "SELECT
        id

     FROM users

     WHERE id=?
     AND status='active'

     LIMIT 1",

    [
        $recipientId
    ]

);


if (!$recipient) {

    echo json_encode([
        "success" => false,
        "message" => "Recipient not found."
    ]);

    exit;
}


/*
 * ==========================================================
 * PREVENT SELF MESSAGE
 * ==========================================================
 */

if ($recipientId === $senderId) {

    echo json_encode([
        "success" => false,
        "message" => "You cannot send a message to yourself."
    ]);

    exit;
}


/*
 * ==========================================================
 * SEND EXISTING DRAFT
 * ==========================================================
 */

if ($draftId > 0) {

    $draft = fetchRow(

        "SELECT
            id

         FROM mail_messages

         WHERE id=?
         AND sender_id=?
         AND is_draft=1

         LIMIT 1",

        [
            $draftId,
            $senderId
        ]

    );


    if ($draft) {


        /*
         * Update the draft.
         */

        $update = updateData(

            "mail_messages",

            [

                "subject" =>
                    $subject !== ''
                    ? $subject
                    : "(No subject)",

                "body" =>
                    $body,

                "parent_id" =>
                    $parentId > 0
                    ? $parentId
                    : null,

                "message_type" =>
                    $messageType,

                "is_draft" =>
                    0

            ],

            [

                "id" =>
                    $draftId,

                "sender_id" =>
                    $senderId

            ]

        );


        if (
            !is_array($update)
            ||
            empty($update['success'])
        ) {

            echo json_encode([
                "success" => false,
                "message" => "Unable to send draft."
            ]);

            exit;

        }


        /*
         * Remove old recipients.
         */

        deleteData(

            "mail_recipients",

            [
                "message_id" =>
                    $draftId
            ]

        );


        /*
         * Add new recipient.
         */

        $recipientResult = insertData(

            "mail_recipients",

            [

                "message_id" =>
                    $draftId,

                "user_id" =>
                    $recipientId,

                "recipient_type" =>
                    "to",

                "is_read" =>
                    0,

                "is_starred" =>
                    0,

                "is_deleted" =>
                    0

            ]

        );


        if (
            !is_array($recipientResult)
            ||
            empty($recipientResult['success'])
        ) {

            echo json_encode([
                "success" => false,
                "message" =>
                    "Message could not be delivered."
            ]);

            exit;

        }


        echo json_encode([

            "success" =>
                true,

            "message" =>
                "Message sent successfully.",

            "message_id" =>
                $draftId

        ]);

        exit;

    }

}


/*
 * ==========================================================
 * CREATE NEW MESSAGE
 * ==========================================================
 */

$message = insertData(

    "mail_messages",

    [

        "sender_id" =>
            $senderId,

        "subject" =>
            $subject !== ''
            ? $subject
            : "(No subject)",

        "body" =>
            $body,

        "parent_id" =>
            $parentId > 0
            ? $parentId
            : null,

        "message_type" =>
            $messageType,

        "is_draft" =>
            0

    ]

);


if (
    !is_array($message)
    ||
    empty($message['success'])
) {

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Unable to create message."

    ]);

    exit;

}


$messageId = (int)(
    $message['insert_id']
);


/*
 * ==========================================================
 * ADD RECIPIENT
 * ==========================================================
 */

$recipientResult = insertData(

    "mail_recipients",

    [

        "message_id" =>
            $messageId,

        "user_id" =>
            $recipientId,

        "recipient_type" =>
            "to",

        "is_read" =>
            0,

        "is_starred" =>
            0,

        "is_deleted" =>
            0

    ]

);


if (
    !is_array($recipientResult)
    ||
    empty($recipientResult['success'])
) {


    /*
     * Roll back the message
     * if recipient insertion fails.
     */

    deleteData(

        "mail_messages",

        [

            "id" =>
                $messageId

        ]

    );


    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Unable to deliver message."

    ]);

    exit;

}


/*
 * ==========================================================
 * SUCCESS
 * ==========================================================
 */

echo json_encode([

    "success" =>
        true,

    "message" =>
        "Message sent successfully.",

    "message_id" =>
        $messageId

]);

exit;