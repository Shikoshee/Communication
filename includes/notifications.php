<?php

require_once __DIR__ . "/config.php";


/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
*/

function createNotification(
    $userId,
    $title,
    $message,
    $type = "system",
    $documentId = null,
    $conversationId = null
) {

    return insertData(
        "notifications",
        [
            "user_id" => $userId,
            "title" => $title,
            "message" => $message,
            "type" => $type,
            "related_document_id" => $documentId,
            "related_conversation_id" => $conversationId
        ]
    );

}


/*
|--------------------------------------------------------------------------
| Get Unread Notification Count
|--------------------------------------------------------------------------
*/

function getUnreadNotificationCount($userId)
{

    $row = fetchRow(

        "SELECT COUNT(*) AS total

         FROM notifications

         WHERE user_id=?

         AND is_read=0",

        [
            (int)$userId
        ]

    );


    return (int)(
        $row['total'] ?? 0
    );

}
