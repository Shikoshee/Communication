<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$conversationId = isset($_GET['conversation_id'])
    ? (int)$_GET['conversation_id']
    : 0;

if (!$conversationId) {
    echo json_encode([]);
    exit;
}

$messages = fetchAll(
"
SELECT
    m.id,
    m.sender_id,
    m.message,
    m.created_at,
    m.read_at,
    m.document_id,
    m.image_path,

    CONCAT(
        u.first_name,
        ' ',
        u.last_name
    ) AS sender,

    d.title,
    d.file_name,
    d.file_path

FROM messages m

LEFT JOIN users u
    ON u.id = m.sender_id

LEFT JOIN documents d
    ON d.id = m.document_id

WHERE m.conversation_id = ?

ORDER BY
    m.created_at ASC,
    m.id ASC
",
[
    $conversationId
]
);

echo json_encode($messages);
exit;
