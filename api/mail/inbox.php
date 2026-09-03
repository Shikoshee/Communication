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

if ($userId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid user."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| INBOX
|--------------------------------------------------------------------------
*/

$messages = fetchAll(

    "SELECT

        m.id,
        m.sender_id,
        m.subject,
        m.body,
        m.parent_id,
        m.message_type,
        m.created_at,
        m.updated_at,

        mr.id AS recipient_record_id,
        mr.recipient_type,
        mr.is_read,
        mr.is_starred,
        mr.read_at,

        u.first_name AS sender_first_name,
        u.last_name AS sender_last_name,
        u.username AS sender_username

     FROM message_recipients mr

     INNER JOIN messages m
        ON m.id = mr.message_id

     INNER JOIN users u
        ON u.id = m.sender_id

     WHERE mr.user_id = ?

     AND mr.is_deleted = 0

     AND m.is_draft = 0

     AND mr.recipient_type IN ('to', 'cc', 'bcc')

     ORDER BY m.created_at DESC",

    [
        $userId
    ]

);


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "data" => $messages

]);

exit;