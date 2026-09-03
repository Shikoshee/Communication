<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Audit.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


// Optional: only administrators can create backups
if (strtolower($user['role']) !== 'admin') {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Access denied."
    ]);

    exit;

}


// Create backup folder if missing

if (!is_dir(BACKUP_DIR)) {

    mkdir(BACKUP_DIR, 0777, true);

}



$filename = DB_NAME . "_" . date("Y-m-d_H-i-s") . ".sql";


$filepath = BACKUP_DIR . $filename;



$command =
    '"' . MYSQLDUMP_PATH . '"' .
    " --user=" . escapeshellarg(DB_USER) .
    " --password=" . escapeshellarg(DB_PASS) .
    " --host=" . escapeshellarg(DB_HOST) .
    " --port=" . DB_PORT .
    " " . escapeshellarg(DB_NAME) .
    " > " . escapeshellarg($filepath);



exec($command, $output, $result);



if ($result !== 0 || !file_exists($filepath)) {


    echo json_encode([

        "success" => false,

        "message" => "Database backup failed.",

        "output" => $output,

        "result" => $result

    ]);


    exit;

}



Audit::log(

    "Created Database Backup",

    "backup",

    null,

    null,

    $filename

);



echo json_encode([

    "success" => true,

    "message" => "Backup created successfully.",

    "file" => $filename

]);