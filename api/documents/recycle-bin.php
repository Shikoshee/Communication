<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json; charset=utf-8");

$user = Auth::getCurrentUser();

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Authentication required."
    ]);
    exit;
}

$userId = (int)($user['id'] ?? 0);

$documents = fetchAll(
    "
    SELECT
        d.id,
        d.title,
        d.deleted_at,
        d.deleted_by,

        CONCAT(
            IFNULL(u.first_name, ''),
            ' ',
            IFNULL(u.last_name, '')
        ) AS owner_name,

        CONCAT(
            IFNULL(du.first_name, ''),
            ' ',
            IFNULL(du.last_name, '')
        ) AS deleted_by_name,

        dept.name AS department_name

    FROM documents d

    LEFT JOIN users u
        ON u.id = d.uploaded_by

    LEFT JOIN users du
        ON du.id = d.deleted_by

    LEFT JOIN departments dept
        ON dept.id = d.department_id

    WHERE d.is_deleted = 1
    AND d.deleted_by = ?

    ORDER BY d.deleted_at DESC
    ",
    [$userId]
);

echo json_encode([
    "success" => true,
    "documents" => $documents
]);

exit;