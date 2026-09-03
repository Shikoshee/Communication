<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$conversationId = isset($_POST['conversation_id'])
    ? (int)$_POST['conversation_id']
    : 0;


if (!$conversationId) {

    echo json_encode([
        "success" => false,
        "message" => "Conversation ID is missing."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify User Belongs To Conversation
|--------------------------------------------------------------------------
*/

$conversation = fetchRow(
    "
    SELECT
        id,
        user_one,
        user_two

    FROM conversations

    WHERE id = ?

    AND (
        user_one = ?
        OR user_two = ?
    )

    LIMIT 1
    ",
    [
        $conversationId,
        $user['id'],
        $user['id']
    ]
);


if (!$conversation) {

    echo json_encode([
        "success" => false,
        "message" => "Conversation not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Mark Incoming Messages As Read
|--------------------------------------------------------------------------
*/

$result = executeQuery(
    "
    UPDATE messages

    SET read_at = NOW()

    WHERE conversation_id = ?

    AND sender_id != ?

    AND read_at IS NULL
    ",
    [
        $conversationId,
        $user['id']
    ]
);


if (!$result['success']) {

    echo json_encode([
        "success" => false,
        "message" => $result['error'] ?? "Unable to mark messages as read."
    ]);

    exit;
}


echo json_encode([
    "success" => true
]);

exit;
