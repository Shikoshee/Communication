<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/time.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$notifications = fetchAll(

"
SELECT

id,
title,
message,
type,
related_document_id,
is_read,
created_at

FROM notifications

WHERE user_id=?

ORDER BY created_at DESC

LIMIT 10

",

[
    $user['id']
]

);

foreach($notifications as &$notification){

    $notification["time"] = timeAgo($notification["created_at"]);

}

unset($notification);

$unread = fetchRow(

"
SELECT

COUNT(*) AS total

FROM notifications

WHERE user_id=?
AND is_read=0

",

[
    $user['id']
]

);

echo json_encode([

    "success" => true,

    "count" => (int)$unread["total"],

    "notifications" => $notifications

]);