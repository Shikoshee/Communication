<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Settings.php";

Auth::protect();

header("Content-Type: application/json");

echo json_encode([
    "success" => true,
    "settings" => Settings::all()
]);