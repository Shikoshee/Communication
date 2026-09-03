<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');

if (!Auth::isAdmin()) {

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;

}

$document_id = (int)($_GET['id'] ?? 0);

if (!$document_id) {

    echo json_encode([
        "success" => false,
        "message" => "Document ID is required."
    ]);

    exit;

}

$permissions = fetchAll(

    "SELECT
        department_id,
        can_view,
        can_edit,
        can_share
     FROM document_permissions
     WHERE document_id = ?",

    [$document_id]

);

echo json_encode([

    "success" => true,

    "permissions" => $permissions

]);
