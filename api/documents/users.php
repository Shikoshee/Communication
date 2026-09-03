<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$users = fetchAll("

SELECT
    id,
    username,
    first_name,
    last_name

FROM users

WHERE status = 'active'

ORDER BY first_name, last_name

");

echo json_encode([
    "success" => true,
    "users" => $users
]);