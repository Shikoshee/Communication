<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

$users = fetchAll("

SELECT
    id,
    username,
    first_name,
    last_name

FROM users

WHERE status='active'

ORDER BY first_name

");

header("Content-Type: application/json");

echo json_encode([
    "success" => true,
    "users" => $users
]);
