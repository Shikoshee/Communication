<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

try {

    $documents = fetchAll(

        "
        SELECT

        id,
        title,
        file_name

        FROM documents

        WHERE status='approved'

        ORDER BY title
        "

    );

    echo json_encode($documents);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load documents."
    ]);

}