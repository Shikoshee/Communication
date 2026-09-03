<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

if (!isset($_GET["file"])) {
    exit("No file specified.");
}

$file = basename($_GET["file"]);
$path = BACKUP_DIR . $file;

if (!file_exists($path)) {
    exit("Backup file not found.");
}

header("Content-Description: File Transfer");
header("Content-Type: application/sql");
header("Content-Disposition: attachment; filename=\"" . $file . "\"");
header("Content-Length: " . filesize($path));

readfile($path);
exit;