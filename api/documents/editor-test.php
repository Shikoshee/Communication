<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

echo json_encode([
    "success" => true,
    "message" => "ONLYOFFICE editor endpoint is reachable",
    "docs_url" => ONLYOFFICE_DOCS_URL,
    "app_url" => APP_URL
]);