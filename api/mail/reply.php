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


$userId = (int)($user['id'] ?? 0);

$messageId = (int)(
    $_POST['message_id'] ?? 0
);

$body = trim(
    (string)(
        $_POST['body'] ?? ''
    )
);


if ($messageId <= 0 || $body === '') {

    echo json_encode([
        "success" => false,
        "message" => "Message and reply body are required."
    ]);

    exit;
}


/*
 * Get original message.
 */

$original = fetchRow(

    "SELECT

        m.id,
        m.sender_id,
        m.subject,
        m.body

     FROM messages m

     WHERE m.id=?

     AND (

        m.sender_id=?

        OR EXISTS (

            SELECT 1
            FROM message_recipients mr
            WHERE mr.message_id=m.id
            AND mr.user_id=?

        )

     )

     LIMIT 1",

    [
        $messageId,
        $userId,
        $userId
    ]

);


if (!$original) {

    echo json_encode([
        "success" => false,
        "message" => "Original message not found."
    ]);

    exit;
}


$recipientId = (int)$original['sender_id'];


/*
 * Do not reply to yourself.
 */

if ($recipientId === $userId) {

    /*
     * Find the first recipient instead.
     */

    $recipient = fetchRow(

        "SELECT user_id

         FROM message_recipients

         WHERE message_id=?
         AND user_id<>?

         ORDER BY id ASC

         LIMIT 1",

        [
            $messageId,
            $userId
        ]

    );


    if ($recipient) {

        $recipientId =
            (int)$recipient['user_id'];

    }

}


if ($recipientId <= 0 || $recipientId === $userId) {

    echo json_encode([
        "success" => false,
        "message" => "There is no valid recipient for this reply."
    ]);

    exit;
}


$subject = $original['subject'];


if (
    stripos(
        trim($subject),
        're:'
    ) !== 0
) {

    $subject = "Re: " . $subject;

}


/*
 * Create reply.
 */

$result = insertData(

    "messages",

    [

        "sender_id" => $userId,

        "subject" => $subject,

        "body" => $body,

        "parent_id" => $messageId,

        "message_type" => "reply",

        "is_draft" => 0

    ]

);


if (
    !is_array($result)
    ||
    empty($result['success'])
) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to create reply."
    ]);

    exit;
}


$replyId = (int)$result['insert_id'];


$recipientResult = insertData(

    "message_recipients",

    [

        "message_id" => $replyId,

        "user_id" => $recipientId,

        "recipient_type" => "to",

        "is_read" => 0,

        "is_starred" => 0,

        "is_deleted" => 0

    ]

);


if (
    !is_array($recipientResult)
    ||
    empty($recipientResult['success'])
) {

    deleteData(
        "messages",
        [
            "id" => $replyId
        ]
    );


    echo json_encode([
        "success" => false,
        "message" => "Unable to deliver reply."
    ]);

    exit;
}


echo json_encode([

    "success" => true,

    "message" => "Reply sent successfully.",

    "message_id" => $replyId

]);

exit;