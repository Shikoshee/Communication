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
    $_GET['id'] ?? 0
);

if ($messageId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid message."
    ]);

    exit;
}


/*
 * ==========================================================
 * MESSAGE
 * ==========================================================
 */

$message = fetchRow("

    SELECT

        m.id,
        m.sender_id,
        m.subject,
        m.body,
        m.parent_id,
        m.message_type,
        m.is_draft,
        m.created_at,

        CONCAT(
            u.first_name,
            ' ',
            u.last_name
        ) AS sender_name,

        u.email AS sender_email

    FROM mail_messages m

    INNER JOIN users u
        ON u.id=m.sender_id

    WHERE m.id=?

    LIMIT 1

", [

    $messageId

]);


if (!$message) {

    echo json_encode([
        "success" => false,
        "message" => "Message not found."
    ]);

    exit;
}


/*
 * ==========================================================
 * ACCESS CHECK
 * ==========================================================
 */

$access = fetchRow("

    SELECT id

    FROM mail_recipients

    WHERE message_id=?

    AND user_id=?

    LIMIT 1

", [

    $messageId,
    $userId

]);


/*
 * Sender can also access their own message.
 */

if (!$access && (int)$message['sender_id'] !== $userId) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;
}


/*
 * ==========================================================
 * RECIPIENTS
 * ==========================================================
 */

$recipients = fetchAll("

    SELECT

        r.id,
        r.user_id,
        r.recipient_type,

        CONCAT(
            u.first_name,
            ' ',
            u.last_name
        ) AS name,

        u.email

    FROM mail_recipients r

    INNER JOIN users u
        ON u.id=r.user_id

    WHERE r.message_id=?

    ORDER BY r.id ASC

", [

    $messageId

]);


/*
 * ==========================================================
 * ATTACHMENTS
 * ==========================================================
 */

$attachments = fetchAll("

    SELECT

        id,
        original_name,
        stored_name,
        mime_type,
        file_size,
        created_at

    FROM mail_attachments

    WHERE message_id=?

    ORDER BY id ASC

", [

    $messageId

]);


/*
 * ==========================================================
 * MARK AS READ
 * ==========================================================
 */

if ($access) {

    updateData(

        "mail_recipients",

        [

            "is_read" => 1,

            "read_at" => date('Y-m-d H:i:s')

        ],

        [

            "message_id" => $messageId,

            "user_id" => $userId

        ]

    );

}


echo json_encode([

    "success" => true,

    "message" => $message,

    "recipients" => $recipients,

    "attachments" => $attachments

]);

exit;