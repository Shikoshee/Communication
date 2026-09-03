<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Audit.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

if (strtolower($user['role']) !== "admin") {

    echo json_encode([
        "success"=>false,
        "message"=>"Access denied."
    ]);

    exit;
}

$file = basename($_POST["file"] ?? "");

$path = BACKUP_DIR . $file;

if (!file_exists($path)) {

    echo json_encode([
        "success"=>false,
        "message"=>"Backup not found."
    ]);

    exit;
}

unlink($path);

Audit::log(
    "Deleted Database Backup",
    "backup",
    null,
    null,
    $file
);

echo json_encode([
    "success"=>true
]);