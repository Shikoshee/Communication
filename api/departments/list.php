<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$departments = fetchAll("
    SELECT
        id,
        name
    FROM departments
    ORDER BY name
");

echo json_encode($departments);