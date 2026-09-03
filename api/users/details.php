<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id = (int)($_GET['id'] ?? 0);

// =======================
// USER
// =======================

$user = fetchRow("

SELECT

u.*,

d.name AS department_name

FROM users u

LEFT JOIN departments d
ON d.id = u.department_id

WHERE u.id = ?

", [$id]);

if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);

    exit;
}


// =======================
// RECENT DOCUMENTS
// =======================

$documents = fetchAll("

SELECT

title,
created_at

FROM documents

WHERE uploaded_by = ?

ORDER BY created_at DESC

LIMIT 5

", [$id]);


// =======================
// RECENT ACTIVITY
// =======================

$activities = fetchAll("

SELECT

activity,
created_at

FROM activity_logs

WHERE user_id = ?

ORDER BY created_at DESC

LIMIT 5

", [$id]);

echo json_encode([

    "success" => true,

    "user" => $user,

    "documents" => $documents,

    "activities" => $activities

]);