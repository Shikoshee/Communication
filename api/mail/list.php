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
 * ==========================================================
 * FOLDER
 * ==========================================================
 */

$folder = strtolower(
    trim(
        (string)($_GET['folder'] ?? 'inbox')
    )
);

$allowedFolders = [
    'inbox',
    'sent',
    'drafts',
    'trash'
];

if (!in_array($folder, $allowedFolders, true)) {

    $folder = 'inbox';

}


/*
 * ==========================================================
 * INBOX
 * ==========================================================
 */

if ($folder === 'inbox') {

    $messages = fetchAll("

        SELECT

            m.id,
            m.sender_id,
            m.subject,
            m.body,
            m.parent_id,
            m.message_type,
            m.created_at,

            r.is_read,
            r.is_starred,

            CONCAT(
                u.first_name,
                ' ',
                u.last_name
            ) AS sender_name,

            u.email AS sender_email

        FROM mail_recipients r

        INNER JOIN mail_messages m
            ON m.id = r.message_id

        INNER JOIN users u
            ON u.id = m.sender_id

        WHERE r.user_id=?

        AND r.recipient_type IN ('to', 'cc')

        AND r.is_deleted=0

        AND m.is_draft=0

        ORDER BY m.created_at DESC

    ", [

        $userId

    ]);

}


/*
 * ==========================================================
 * SENT
 * ==========================================================
 */

elseif ($folder === 'sent') {

    $messages = fetchAll("

        SELECT

            m.id,
            m.sender_id,
            m.subject,
            m.body,
            m.parent_id,
            m.message_type,
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

        WHERE m.sender_id=?

        AND m.is_draft=0

        AND EXISTS (

            SELECT 1

            FROM mail_recipients r

            WHERE r.message_id=m.id

        )

        ORDER BY m.created_at DESC

    ", [

        $userId

    ]);

}


/*
 * ==========================================================
 * DRAFTS
 * ==========================================================
 */

elseif ($folder === 'drafts') {

    $messages = fetchAll("

        SELECT

            m.id,
            m.sender_id,
            m.subject,
            m.body,
            m.parent_id,
            m.message_type,
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

        WHERE m.sender_id=?

        AND m.is_draft=1

        ORDER BY m.updated_at DESC

    ", [

        $userId

    ]);

}


/*
 * ==========================================================
 * TRASH
 * ==========================================================
 */

else {

    $messages = fetchAll("

        SELECT

            m.id,
            m.sender_id,
            m.subject,
            m.body,
            m.parent_id,
            m.message_type,
            m.created_at,

            r.is_read,
            r.is_starred,

            CONCAT(
                u.first_name,
                ' ',
                u.last_name
            ) AS sender_name,

            u.email AS sender_email

        FROM mail_recipients r

        INNER JOIN mail_messages m
            ON m.id=r.message_id

        INNER JOIN users u
            ON u.id=m.sender_id

        WHERE r.user_id=?

        AND r.is_deleted=1

        ORDER BY m.created_at DESC

    ", [

        $userId

    ]);

}


echo json_encode([

    "success" => true,

    "folder" => $folder,

    "data" => $messages

]);

exit;