<?php

ob_start();

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/ApprovalRouter.php";

Auth::protect();

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse($success, $message, $extra = [])
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($success ? 200 : 400);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Request method
    |--------------------------------------------------------------------------
    */

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        jsonResponse(
            false,
            "Invalid request method."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Document ID
    |--------------------------------------------------------------------------
    */

    $id = (int)($_POST["id"] ?? 0);

    if ($id <= 0) {

        jsonResponse(
            false,
            "Invalid document ID."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Current user
    |--------------------------------------------------------------------------
    */

    $user = Auth::getCurrentUser();

    if (!$user || empty($user["id"])) {

        jsonResponse(
            false,
            "User authentication failed."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Permission check
    |--------------------------------------------------------------------------
    */

    if (
        !ApprovalRouter::canUserApproveDocument(
            $user["id"],
            $id
        )
    ) {

        jsonResponse(
            false,
            "You are not authorized to modify this document."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get document
    |--------------------------------------------------------------------------
    */

    $document = fetchRow(
        "
        SELECT
            id,
            status,
            reviewed_file,
            reviewed_by,
            reviewed_at,
            approval_signature
        FROM documents
        WHERE id = ?
        LIMIT 1
        ",
        [$id]
    );


    if (!$document) {

        jsonResponse(
            false,
            "Document not found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get signed file path
    |--------------------------------------------------------------------------
    */

    $reviewedFile = trim(
        (string)($document["reviewed_file"] ?? "")
    );


    if ($reviewedFile === "") {

        jsonResponse(
            false,
            "There is no signed copy to remove."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Application root
    |--------------------------------------------------------------------------
    */

    $appRoot = realpath(
        dirname(__DIR__, 2)
    );


    if ($appRoot === false) {

        jsonResponse(
            false,
            "Application directory could not be located."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Documents directory
    |--------------------------------------------------------------------------
    */

    $documentsDirectory = realpath(
        $appRoot .
        DIRECTORY_SEPARATOR .
        "uploads" .
        DIRECTORY_SEPARATOR .
        "documents"
    );


    if ($documentsDirectory === false) {

        jsonResponse(
            false,
            "Document storage directory could not be located."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize stored path
    |--------------------------------------------------------------------------
    */

    $relativePath = str_replace(
        ["/", "\\"],
        DIRECTORY_SEPARATOR,
        $reviewedFile
    );

    $relativePath = ltrim(
        $relativePath,
        DIRECTORY_SEPARATOR
    );


    /*
    |--------------------------------------------------------------------------
    | Build physical file path
    |--------------------------------------------------------------------------
    */

    $filePath =
        $appRoot .
        DIRECTORY_SEPARATOR .
        $relativePath;


    $realFilePath = realpath($filePath);


    /*
    |--------------------------------------------------------------------------
    | Delete signed PDF
    |--------------------------------------------------------------------------
    */

    if ($realFilePath !== false) {

        $documentsDirectoryWithSeparator =
            rtrim(
                $documentsDirectory,
                DIRECTORY_SEPARATOR
            ) .
            DIRECTORY_SEPARATOR;


        /*
        | Security check:
        | Make sure the file is actually inside
        | uploads/documents/
        */

        if (
            strpos(
                $realFilePath,
                $documentsDirectoryWithSeparator
            ) !== 0
        ) {

            jsonResponse(
                false,
                "The signed file location is not allowed."
            );
        }


        if (is_file($realFilePath)) {

            if (!unlink($realFilePath)) {

                jsonResponse(
                    false,
                    "The signed PDF could not be deleted from the server."
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Clear signed-document information
    |--------------------------------------------------------------------------
    */

    $updated = updateData(
        "documents",
        [
            "reviewed_file" => null,
            "reviewed_by" => null,
            "reviewed_at" => null,
            "approval_signature" => null
        ],
        [
            "id" => $id
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Check database update
    |--------------------------------------------------------------------------
    */

    if (
        !is_array($updated) ||
        empty($updated["success"])
    ) {

        $databaseError =
            is_array($updated) &&
            !empty($updated["error"])
                ? $updated["error"]
                : "Unknown database error.";


        error_log(
            "Remove signed document database error: " .
            $databaseError
        );


        jsonResponse(
            false,
            "The signed file was removed, but the database could not be updated."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        "Signed copy removed successfully. You can now sign the document again."
    );


} catch (Throwable $e) {

    error_log(
        "Remove signed document error: " .
        $e->getMessage() .
        " | File: " .
        $e->getFile() .
        " | Line: " .
        $e->getLine()
    );


    jsonResponse(
        false,
        "An unexpected server error occurred while removing the signed copy."
    );
}