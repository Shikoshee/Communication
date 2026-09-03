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

if ($messageId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid message."
    ]);

    exit;
}


$result = updateData(

    "mail_recipients",

    [

        "is_read" => 0,

        "read_at" => null

    ],

    [

        "message_id" => $messageId,

        "user_id" => $userId

    ]

);


echo json_encode([

    "success" =>
        is_array($result)
        &&
        !empty($result['success']),

    "message" => "Message marked as unread."

]);

exit;