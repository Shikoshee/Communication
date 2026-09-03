<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");


$files = [];


if (is_dir(BACKUP_DIR)) {


    foreach(scandir(BACKUP_DIR) as $file){


        if($file == "." || $file == ".."){
            continue;
        }


        $path = BACKUP_DIR . $file;


        if(is_file($path)){


            $files[] = [

                "name" => $file,

                "size" => round(
                    filesize($path) / 1024,
                    2
                ) . " KB",

                "date" => date(
                    "d M Y H:i",
                    filemtime($path)
                )

            ];

        }

    }

}


echo json_encode([

    "success" => true,

    "backups" => $files

]);