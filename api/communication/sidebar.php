<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$users = fetchAll(
"
SELECT
    u.id,
    u.first_name,
    u.last_name,

    lm.message AS last_message,
    lm.created_at AS last_message_at,
    lm.id AS last_message_id,

    COALESCE(um.unread_count, 0) AS unread_count

FROM users u

LEFT JOIN conversations c
    ON (
        (c.user_one = ? AND c.user_two = u.id)
        OR
        (c.user_two = ? AND c.user_one = u.id)
    )

LEFT JOIN messages lm
    ON lm.id = (
        SELECT m2.id
        FROM messages m2
        WHERE m2.conversation_id = c.id
        ORDER BY m2.created_at DESC, m2.id DESC
        LIMIT 1
    )

LEFT JOIN (
    SELECT
        conversation_id,
        COUNT(*) AS unread_count

    FROM messages

    WHERE sender_id != ?
      AND read_at IS NULL

    GROUP BY conversation_id

) um
    ON um.conversation_id = c.id

WHERE u.id != ?
AND u.status = 'active'

ORDER BY

    CASE
        WHEN lm.created_at IS NULL THEN 1
        ELSE 0
    END ASC,

    lm.created_at DESC,

    lm.id DESC,

    u.first_name ASC
",
[
    $user['id'],
    $user['id'],
    $user['id'],
    $user['id']
]
);

echo json_encode([
    "success" => true,
    "users" => $users
]);

exit;
