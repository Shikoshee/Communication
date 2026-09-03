<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


$rows = fetchAll(
    "
    SELECT

        CASE
            WHEN c.user_one = ?
                THEN c.user_two
            ELSE c.user_one
        END AS user_id,

        COUNT(m.id) AS unread_count

    FROM conversations c

    INNER JOIN messages m
        ON m.conversation_id = c.id

    WHERE
        (
            c.user_one = ?
            OR
            c.user_two = ?
        )

        AND m.sender_id != ?

        AND m.read_at IS NULL

    GROUP BY
        c.id,
        CASE
            WHEN c.user_one = ?
                THEN c.user_two
            ELSE c.user_one
        END

    ",
    [
        $user['id'],
        $user['id'],
        $user['id'],
        $user['id'],
        $user['id']
    ]
);


$unread = [];


foreach ($rows as $row) {

    $unread[
        (int)$row['user_id']
    ] = (int)$row['unread_count'];

}


echo json_encode([
    "success" => true,
    "unread" => $unread
]);

exit;
