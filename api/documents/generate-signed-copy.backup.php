<?php

/*
|--------------------------------------------------------------------------
| Communication System
| Generate Signed Copy
|--------------------------------------------------------------------------
|
| Receives the PDF exported by ONLYOFFICE, downloads it to the server,
| stores it as documents.reviewed_file and returns the saved file path.
|
*/


/*
|--------------------------------------------------------------------------
| Application configuration
|--------------------------------------------------------------------------
*/

require_once "../../includes/config.php";


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| HTTP_ORIGIN contains ONLY:
|
| https://example.com
|
| It does NOT contain:
|
| /communication
|
| Therefore we extract only scheme + host + port from APP_URL and
| ONLYOFFICE_PUBLIC_APP_URL before comparing them.
|
*/


$requestOrigin =
    trim(
        (string)(
            $_SERVER["HTTP_ORIGIN"] ?? ""
        )
    );


$allowedOrigins = [];


/*
|--------------------------------------------------------------------------
| Helper: Add origin from URL
|--------------------------------------------------------------------------
*/

function addAllowedOrigin(
    array &$allowedOrigins,
    string $url
): void {

    $url =
        trim($url);

    if ($url === "") {
        return;
    }


    $parsed =
        parse_url($url);


    if (
        !$parsed ||
        empty($parsed["scheme"]) ||
        empty($parsed["host"])
    ) {

        return;
    }


    $origin =
        strtolower(
            $parsed["scheme"] .
            "://" .
            $parsed["host"]
        );


    /*
     * Preserve non-standard ports.
     */

    if (
        isset($parsed["port"])
    ) {

        $origin .=
            ":" .
            $parsed["port"];
    }


    if (
        !in_array(
            $origin,
            $allowedOrigins,
            true
        )
    ) {

        $allowedOrigins[] =
            $origin;
    }
}


/*
|--------------------------------------------------------------------------
| ONLYOFFICE public application URL
|--------------------------------------------------------------------------
*/

if (
    defined("ONLYOFFICE_PUBLIC_APP_URL")
) {

    addAllowedOrigin(
        $allowedOrigins,
        (string)ONLYOFFICE_PUBLIC_APP_URL
    );
}


/*
|--------------------------------------------------------------------------
| Normal application URL
|--------------------------------------------------------------------------
*/

if (
    defined("APP_URL")
) {

    addAllowedOrigin(
        $allowedOrigins,
        (string)APP_URL
    );
}


/*
|--------------------------------------------------------------------------
| Handle CORS
|--------------------------------------------------------------------------
*/

if (
    $requestOrigin !== ""
) {

    /*
     * Normalize browser origin.
     */

    $normalizedRequestOrigin =
        strtolower(
            rtrim(
                $requestOrigin,
                "/"
            )
        );


    /*
     * If the browser origin is one of our known origins,
     * explicitly allow it.
     */

    if (
        in_array(
            $normalizedRequestOrigin,
            $allowedOrigins,
            true
        )
    ) {

        header(
            "Access-Control-Allow-Origin: " .
            $requestOrigin
        );

        header(
            "Access-Control-Allow-Credentials: true"
        );

        header(
            "Access-Control-Allow-Methods: POST, OPTIONS"
        );

        header(
            "Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With"
        );

        header(
            "Access-Control-Max-Age: 86400"
        );

        header(
            "Vary: Origin"
        );
    }
}


/*
|--------------------------------------------------------------------------
| Handle browser preflight request
|--------------------------------------------------------------------------
|
| The browser sends OPTIONS before the JSON POST because the request
| uses Content-Type: application/json.
|
| This MUST happen before Auth::protect().
|
*/

if (
    strtoupper(
        $_SERVER["REQUEST_METHOD"] ?? ""
    ) === "OPTIONS"
) {

    http_response_code(204);

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

require_once "../../includes/auth.php";
require_once "../../includes/ApprovalRouter.php";

Auth::protect();


/*
|--------------------------------------------------------------------------
| JSON response header
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: application/json; charset=UTF-8"
);


/*
|--------------------------------------------------------------------------
| Helper: JSON response
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $extra = [],
    int $httpCode = 200
): void {

    http_response_code(
        $httpCode
    );


    echo json_encode(
        array_merge(
            [
                "success" =>
                    $success,

                "message" =>
                    $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| Get current user
|--------------------------------------------------------------------------
*/

$user =
    Auth::getCurrentUser();


if (
    !$user ||
    empty($user["id"])
) {

    jsonResponse(
        false,
        "Unable to determine the current user.",
        [],
        401
    );
}


/*
|--------------------------------------------------------------------------
| Read JSON request
|--------------------------------------------------------------------------
*/

$rawInput =
    file_get_contents(
        "php://input"
    );


$data =
    json_decode(
        $rawInput,
        true
    );


if (
    !is_array($data)
) {

    jsonResponse(
        false,
        "Invalid request data.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Document ID
|--------------------------------------------------------------------------
*/

$id =
    (int)(
        $data["id"] ?? 0
    );


if (
    $id <= 0
) {

    jsonResponse(
        false,
        "Invalid document ID.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| ONLYOFFICE exported PDF URL
|--------------------------------------------------------------------------
*/

$fileUrl =
    trim(
        (string)(
            $data["file_url"] ?? ""
        )
    );


if (
    $fileUrl === ""
) {

    jsonResponse(
        false,
        "The exported PDF URL is missing.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Validate document
|--------------------------------------------------------------------------
*/

$document =
    fetchRow(
        "SELECT
            d.*,
            CONCAT(
                u.first_name,
                ' ',
                u.last_name
            ) AS uploader

         FROM documents d

         LEFT JOIN users u
            ON u.id = d.uploaded_by

         WHERE d.id = ?

         LIMIT 1",
        [$id]
    );


if (
    !$document
) {

    jsonResponse(
        false,
        "Document not found.",
        [],
        404
    );
}


/*
|--------------------------------------------------------------------------
| Verify approval permission
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
        "You do not have permission to sign this document.",
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Validate ONLYOFFICE URL
|--------------------------------------------------------------------------
*/

$parsedUrl =
    parse_url(
        $fileUrl
    );


if (
    !$parsedUrl ||
    empty($parsedUrl["scheme"]) ||
    empty($parsedUrl["host"])
) {

    jsonResponse(
        false,
        "Invalid ONLYOFFICE export URL.",
        [],
        400
    );
}


$scheme =
    strtolower(
        $parsedUrl["scheme"]
    );


if (
    !in_array(
        $scheme,
        [
            "http",
            "https"
        ],
        true
    )
) {

    jsonResponse(
        false,
        "The exported file URL must use HTTP or HTTPS.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Temporary directory
|--------------------------------------------------------------------------
*/

$tempDirectory =
    dirname(
        __DIR__,
        2
    ) .
    DIRECTORY_SEPARATOR .
    "uploads" .
    DIRECTORY_SEPARATOR .
    "documents" .
    DIRECTORY_SEPARATOR .
    "temp";


if (
    !is_dir(
        $tempDirectory
    )
) {

    if (
        !mkdir(
            $tempDirectory,
            0775,
            true
        )
    ) {

        jsonResponse(
            false,
            "Unable to create temporary document directory.",
            [],
            500
        );
    }
}


/*
|--------------------------------------------------------------------------
| Temporary PDF
|--------------------------------------------------------------------------
*/

$tempFile =
    tempnam(
        $tempDirectory,
        "onlyoffice_"
    );


if (
    $tempFile === false
) {

    jsonResponse(
        false,
        "Unable to create temporary PDF file.",
        [],
        500
    );
}


$downloadedBytes =
    0;


$maxFileSize =
    defined("MAX_FILE_SIZE")
        ? (int)MAX_FILE_SIZE
        : 50 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| Open temporary file
|--------------------------------------------------------------------------
*/

$fp =
    fopen(
        $tempFile,
        "wb"
    );


if (
    $fp === false
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "Unable to open temporary PDF file.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Download PDF from ONLYOFFICE
|--------------------------------------------------------------------------
*/

$curl =
    curl_init(
        $fileUrl
    );


if (
    $curl === false
) {

    fclose(
        $fp
    );

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "Unable to initialize PDF download.",
        [],
        500
    );
}


curl_setopt_array(
    $curl,
    [

        CURLOPT_FOLLOWLOCATION =>
            true,

        CURLOPT_MAXREDIRS =>
            5,

        CURLOPT_CONNECTTIMEOUT =>
            15,

        CURLOPT_TIMEOUT =>
            120,

        CURLOPT_SSL_VERIFYPEER =>
            true,

        CURLOPT_SSL_VERIFYHOST =>
            2,

        CURLOPT_USERAGENT =>
            "Communication-System-ONLYOFFICE/1.0",

        CURLOPT_FAILONERROR =>
            false,

        CURLOPT_HEADER =>
            false,

        CURLOPT_WRITEFUNCTION =>
            function (
                $curlHandle,
                $chunk
            ) use (
                $fp,
                &$downloadedBytes,
                $maxFileSize
            ) {

                $length =
                    strlen(
                        $chunk
                    );


                $downloadedBytes +=
                    $length;


                /*
                 * Stop oversized downloads.
                 */

                if (
                    $downloadedBytes >
                    $maxFileSize
                ) {

                    return 0;
                }


                return fwrite(
                    $fp,
                    $chunk
                );
            }

    ]
);


$curlResult =
    curl_exec(
        $curl
    );


$curlError =
    curl_error(
        $curl
    );


$curlErrno =
    curl_errno(
        $curl
    );


$httpCode =
    (int)curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


$contentType =
    strtolower(
        (string)curl_getinfo(
            $curl,
            CURLINFO_CONTENT_TYPE
        )
    );


curl_close(
    $curl
);


fclose(
    $fp
);


/*
|--------------------------------------------------------------------------
| Check cURL result
|--------------------------------------------------------------------------
*/

if (
    $curlResult === false
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "Unable to download the PDF from ONLYOFFICE: " .
        (
            $curlError
                ?: "Unknown download error."
        ),
        [
            "curl_error_code" =>
                $curlErrno
        ],
        502
    );
}


/*
|--------------------------------------------------------------------------
| Check HTTP status
|--------------------------------------------------------------------------
*/

if (
    $httpCode < 200 ||
    $httpCode >= 300
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "ONLYOFFICE returned HTTP status " .
        $httpCode .
        " while exporting the PDF.",
        [],
        502
    );
}


/*
|--------------------------------------------------------------------------
| Check downloaded file
|--------------------------------------------------------------------------
*/

if (
    !file_exists(
        $tempFile
    )
) {

    jsonResponse(
        false,
        "The exported PDF was not downloaded.",
        [],
        502
    );
}


$fileSize =
    filesize(
        $tempFile
    );


if (
    $fileSize === false ||
    $fileSize <= 0
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "The exported PDF is empty.",
        [],
        422
    );
}


if (
    $fileSize >
    $maxFileSize
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "The exported PDF is larger than the allowed file size.",
        [],
        413
    );
}


/*
|--------------------------------------------------------------------------
| Verify PDF signature
|--------------------------------------------------------------------------
*/

$fileHandle =
    fopen(
        $tempFile,
        "rb"
    );


if (
    $fileHandle === false
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "Unable to verify the exported PDF.",
        [],
        500
    );
}


$signature =
    fread(
        $fileHandle,
        5
    );


fclose(
    $fileHandle
);


if (
    $signature !== "%PDF-"
) {

    @unlink(
        $tempFile
    );

    jsonResponse(
        false,
        "ONLYOFFICE did not return a valid PDF file.",
        [
            "content_type" =>
                $contentType
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Final documents directory
|--------------------------------------------------------------------------
*/

$documentsDirectory =
    dirname(
        __DIR__,
        2
    ) .
    DIRECTORY_SEPARATOR .
    "uploads" .
    DIRECTORY_SEPARATOR .
    "documents";


if (
    !is_dir(
        $documentsDirectory
    )
) {

    if (
        !mkdir(
            $documentsDirectory,
            0775,
            true
        )
    ) {

        @unlink(
            $tempFile
        );

        jsonResponse(
            false,
            "Unable to create documents upload directory.",
            [],
            500
        );
    }
}


/*
|--------------------------------------------------------------------------
| Generate final filename
|--------------------------------------------------------------------------
*/

$originalName =
    $document["file_name"] ??
    (
        "document_" .
        $id
    );


$originalBaseName =
    pathinfo(
        $originalName,
        PATHINFO_FILENAME
    );


$originalBaseName =
    preg_replace(
        '/[^A-Za-z0-9_\-]+/',
        '_',
        $originalBaseName
    );


$originalBaseName =
    trim(
        $originalBaseName,
        "_-"
    );


if (
    $originalBaseName === ""
) {

    $originalBaseName =
        "document_" .
        $id;
}


/*
|--------------------------------------------------------------------------
| Final signed filename
|--------------------------------------------------------------------------
*/

$finalFileName =
    $originalBaseName .
    "_signed_" .
    date("Ymd_His") .
    "_" .
    bin2hex(
        random_bytes(4)
    ) .
    ".pdf";


$finalPath =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Move temporary PDF to final location
|--------------------------------------------------------------------------
*/

if (
    !rename(
        $tempFile,
        $finalPath
    )
) {

    /*
     * Fallback to copy.
     */

    if (
        !copy(
            $tempFile,
            $finalPath
        )
    ) {

        @unlink(
            $tempFile
        );

        jsonResponse(
            false,
            "Unable to save the signed PDF.",
            [],
            500
        );
    }


    @unlink(
        $tempFile
    );
}


/*
|--------------------------------------------------------------------------
| Verify final PDF
|--------------------------------------------------------------------------
*/

if (
    !file_exists(
        $finalPath
    ) ||
    filesize(
        $finalPath
    ) <= 0
) {

    @unlink(
        $finalPath
    );

    jsonResponse(
        false,
        "The signed PDF could not be verified after saving.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Database path
|--------------------------------------------------------------------------
*/

$reviewedFile =
    "uploads/documents/" .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Update document
|--------------------------------------------------------------------------
*/

$updated =
    updateData(
        "documents",
        [

            "reviewed_file" =>
                $reviewedFile,

            "reviewed_by" =>
                $user["id"],

            "reviewed_at" =>
                date(
                    "Y-m-d H:i:s"
                )

        ],
        "id = ?",
        [$id]
    );


if (
    !$updated
) {

    /*
     * Remove generated file if DB update failed.
     */

    @unlink(
        $finalPath
    );

    jsonResponse(
        false,
        "The signed PDF was created, but the document record could not be updated.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Public/relative reviewed file URL
|--------------------------------------------------------------------------
*/

$reviewedUrl =
    "../../" .
    $reviewedFile;


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    "Signed PDF generated and saved successfully.",
    [

        "document_id" =>
            $id,

        "reviewed_file" =>
            $reviewedFile,

        "reviewed_url" =>
            $reviewedUrl,

        "reviewed_by" =>
            $user["id"],

        "reviewed_at" =>
            date(
                "Y-m-d H:i:s"
            )

    ]
);