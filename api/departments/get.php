<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id = (int)($_GET['id'] ?? 0);

$department = fetchRow(
    "SELECT * FROM departments WHERE id=?",
    [$id]
);

if (!$department) {

    echo json_encode([
        "success" => false,
        "message" => "Department not found."
    ]);

    exit;
}

echo json_encode([
    "success" => true,
    "department" => $department
]);