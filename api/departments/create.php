<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);
    exit;
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = strtolower(trim($_POST['status'] ?? 'active'));

if ($name == "") {

    echo json_encode([
        "success" => false,
        "message" => "Department name is required."
    ]);

    exit;
}
$exists = fetchRow(
    "SELECT id FROM departments WHERE name = ?",
    [$name]
);

if ($exists) {

    echo json_encode([
        "success" => false,
        "message" => "Department already exists."
    ]);

    exit;
}

$result = insertData("departments", [

    "name" => $name,
    "description" => $description,
    "status" => $status

]);

if (!$result['success']) {

    echo json_encode([
        "success" => false,
        "message" => $result['error']
    ]);

    exit;
}


/* Activity Log */

$departmentId = $result['insert_id'];

insertData("activity_logs", [

    "user_id"       => $user['id'],
    "department_id" => $departmentId,
    "activity"      => "Created department ".$name,
    "activity_type" => "edit"

]);


echo json_encode([
    "success" => true,
    "message" => "Department created successfully."
]);