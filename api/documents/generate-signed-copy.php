
<?php

/*
|--------------------------------------------------------------------------
| Communication System
| Generate Signed Copy
|--------------------------------------------------------------------------
|
| Receives the PDF exported by ONLYOFFICE, validates it, saves it, and
| makes it the main document by updating:
|
|   documents.file_path
|   documents.file_name
|   documents.reviewed_file
|
| The original file on disk is preserved.
| Generating a signed copy does not approve the document.
|
*/

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/ApprovalRouter.php";


/*
|--------------------------------------------------------------------------
| Start session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| JSON response
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=UTF-8");

function jsonResponse(
    bool $success,
    string $message,
    array $extra = [],
    int $httpCode = 200
): void {
    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

function addAllowedOrigin(array &$allowedOrigins, string $url): void
{
    $url = trim($url);

    if ($url === "") {
        return;
    }

    $parsed = parse_url($url);

    if (
        !$parsed ||
        empty($parsed["scheme"]) ||
        empty($parsed["host"])
    ) {
        return;
    }

    $scheme = strtolower($parsed["scheme"]);
    $host = strtolower($parsed["host"]);

    if (!in_array($scheme, ["http", "https"], true)) {
        return;
    }

    $origin = $scheme . "://" . $host;

    if (isset($parsed["port"])) {
        $origin .= ":" . (int)$parsed["port"];
    }

    if (!in_array($origin, $allowedOrigins, true)) {
        $allowedOrigins[] = $origin;
    }
}

$requestOrigin = trim(
    (string)($_SERVER["HTTP_ORIGIN"] ?? "")
);

$allowedOrigins = [];

if (defined("APP_URL")) {
    addAllowedOrigin($allowedOrigins, (string)APP_URL);
}

if (defined("ONLYOFFICE_PUBLIC_APP_URL")) {
    addAllowedOrigin(
        $allowedOrigins,
        (string)ONLYOFFICE_PUBLIC_APP_URL
    );
}

if (defined("ONLYOFFICE_DOCS_URL")) {
    addAllowedOrigin(
        $allowedOrigins,
        (string)ONLYOFFICE_DOCS_URL
    );
}

$originAllowed = false;

if ($requestOrigin !== "") {
    $normalizedOrigin = strtolower(rtrim($requestOrigin, "/"));

    if (in_array($normalizedOrigin, $allowedOrigins, true)) {
        $originAllowed = true;

        header("Access-Control-Allow-Origin: " . $requestOrigin);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header(
            "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept"
        );
        header("Access-Control-Max-Age: 86400");
        header("Vary: Origin");
    }
}


/*
|--------------------------------------------------------------------------
| Handle OPTIONS preflight
|--------------------------------------------------------------------------
*/

$requestMethod = strtoupper(
    (string)($_SERVER["REQUEST_METHOD"] ?? "")
);

if ($requestMethod === "OPTIONS") {
    if ($requestOrigin !== "" && !$originAllowed) {
        http_response_code(403);
        exit;
    }

    http_response_code(204);
    exit;
}


/*
|--------------------------------------------------------------------------
| POST only
|--------------------------------------------------------------------------
*/

if ($requestMethod !== "POST") {
    header("Allow: POST, OPTIONS");

    jsonResponse(
        false,
        "Only POST requests are allowed.",
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| Validate ONLYOFFICE configuration
|--------------------------------------------------------------------------
*/

if (
    !defined("ONLYOFFICE_JWT_SECRET") ||
    trim((string)ONLYOFFICE_JWT_SECRET) === ""
) {
    error_log(
        "Generate signed copy: ONLYOFFICE_JWT_SECRET is not configured."
    );

    jsonResponse(
        false,
        "The document signing service is not configured.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Validate optional HMAC identity token
|--------------------------------------------------------------------------
|
| Expected signed payload:
|
| documentId|userId
|
*/

$tokenDocumentId = 0;
$tokenUserId = 0;

$requestDocumentId = (int)($_GET["documentId"] ?? 0);
$requestUserId = (int)($_GET["userId"] ?? 0);
$requestToken = trim((string)($_GET["token"] ?? ""));

$tokenWasSupplied = (
    $requestDocumentId > 0 ||
    $requestUserId > 0 ||
    $requestToken !== ""
);

if ($tokenWasSupplied) {
    if (
        $requestDocumentId <= 0 ||
        $requestUserId <= 0 ||
        $requestToken === ""
    ) {
        jsonResponse(
            false,
            "Incomplete document authentication details.",
            [],
            401
        );
    }

    $tokenPayload = $requestDocumentId . "|" . $requestUserId;

    $expectedToken = hash_hmac(
        "sha256",
        $tokenPayload,
        ONLYOFFICE_JWT_SECRET
    );

    if (!hash_equals($expectedToken, $requestToken)) {
        jsonResponse(
            false,
            "Invalid document authentication token.",
            [],
            401
        );
    }

    $tokenDocumentId = $requestDocumentId;
    $tokenUserId = $requestUserId;
}


/*
|--------------------------------------------------------------------------
| Identify authenticated user
|--------------------------------------------------------------------------
*/

$user = null;

if (!empty($_SESSION["user_id"])) {
    $user = Auth::getCurrentUser();
}

if (!$user && $tokenUserId > 0) {
    $user = fetchRow(
        "
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
        ",
        [$tokenUserId]
    );
}

if (!$user || empty($user["id"])) {
    jsonResponse(
        false,
        "Authentication required.",
        [],
        401
    );
}

$userId = (int)$user["id"];

if (
    $tokenUserId > 0 &&
    $userId !== $tokenUserId
) {
    jsonResponse(
        false,
        "User authentication mismatch.",
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Read JSON request
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents("php://input");

$data = json_decode($rawInput, true);

if (!is_array($data)) {
    jsonResponse(
        false,
        "Invalid request data.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Resolve and validate document ID
|--------------------------------------------------------------------------
*/

$id = (int)($data["id"] ?? 0);

if ($id <= 0 && $tokenDocumentId > 0) {
    $id = $tokenDocumentId;
}

if ($id <= 0) {
    jsonResponse(
        false,
        "Invalid document ID.",
        [],
        400
    );
}

if (
    $tokenDocumentId > 0 &&
    $id !== $tokenDocumentId
) {
    jsonResponse(
        false,
        "Document authentication mismatch.",
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Validate exported PDF URL
|--------------------------------------------------------------------------
*/

$fileUrl = trim((string)($data["file_url"] ?? ""));

if ($fileUrl === "") {
    jsonResponse(
        false,
        "The exported PDF URL is missing.",
        [],
        400
    );
}

$parsedUrl = parse_url($fileUrl);

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

$urlScheme = strtolower($parsedUrl["scheme"]);
$urlHost = strtolower($parsedUrl["host"]);

if ($urlScheme !== "https") {
    jsonResponse(
        false,
        "The ONLYOFFICE export URL must use HTTPS.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Restrict export URL to configured ONLYOFFICE host
|--------------------------------------------------------------------------
*/

$onlyofficeHost = "";

if (defined("ONLYOFFICE_DOCS_URL")) {
    $onlyofficeParsed = parse_url(
        (string)ONLYOFFICE_DOCS_URL
    );

    if (
        $onlyofficeParsed &&
        !empty($onlyofficeParsed["host"])
    ) {
        $onlyofficeHost = strtolower(
            $onlyofficeParsed["host"]
        );
    }
}

if (
    $onlyofficeHost === "" ||
    $urlHost !== $onlyofficeHost ||
    !empty($parsedUrl["user"]) ||
    !empty($parsedUrl["pass"])
) {
    jsonResponse(
        false,
        "The export URL does not match the configured ONLYOFFICE server.",
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Load document and uploader
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "
    SELECT
        d.*,
        CONCAT(
            COALESCE(u.first_name, ''),
            ' ',
            COALESCE(u.last_name, '')
        ) AS uploader
    FROM documents d
    LEFT JOIN users u
        ON u.id = d.uploaded_by
    WHERE d.id = ?
      AND COALESCE(d.is_deleted, 0) = 0
    LIMIT 1
    ",
    [$id]
);

if (!$document) {
    jsonResponse(
        false,
        "Document not found.",
        [],
        404
    );
}


/*
|--------------------------------------------------------------------------
| Check permission
|--------------------------------------------------------------------------
|
| The user must either:
| 1. Be authorized to approve the document; or
| 2. Be its uploader while it is pending.
|
*/

/*
|--------------------------------------------------------------------------
| Determine authorization to generate a signed copy
|--------------------------------------------------------------------------
|
| Allow:
| 1. An authorized approver; or
| 2. The document uploader, provided the document has not reached
|    a final status.
|
| Generating a signed copy never approves the document.
|
*/

$userId = (int)$user["id"];

$canApproveDocument = ApprovalRouter::canUserApproveDocument(
    $userId,
    $id
);

$isUploader = (
    (int)($document["uploaded_by"] ?? 0) === $userId
);

$documentStatus = strtolower(
    trim((string)($document["status"] ?? "pending"))
);

if ($documentStatus === "") {
    $documentStatus = "pending";
}

/*
 * Prevent changes to documents that have reached a final state.
 * Other workflow statuses remain eligible for signing by the uploader.
 */
$finalStatuses = [
    "approved",
    "rejected",
    "cancelled",
    "canceled",
    "deleted"
];

$isFinalStatus = in_array(
    $documentStatus,
    $finalStatuses,
    true
);

$authorizedToGenerateSignedCopy = (
    $canApproveDocument ||
    ($isUploader && !$isFinalStatus)
);

if (!$authorizedToGenerateSignedCopy) {
    error_log(
        "Generate signed copy authorization denied. " .
        "Document ID: " . $id .
        "; User ID: " . $userId .
        "; Uploader ID: " . (int)($document["uploaded_by"] ?? 0) .
        "; Status: " . $documentStatus .
        "; Can approve: " . ($canApproveDocument ? "yes" : "no")
    );

    jsonResponse(
        false,
        "You are not authorized to generate a signed copy of this document. Check that you are the document uploader or an authorized approver.",
        [],
        403
    );
}

/*
|--------------------------------------------------------------------------
| Prepare storage directories
|--------------------------------------------------------------------------
*/

$appRoot = dirname(__DIR__, 2);

$documentsDirectory =
    $appRoot .
    DIRECTORY_SEPARATOR . "uploads" .
    DIRECTORY_SEPARATOR . "documents";

$tempDirectory =
    $documentsDirectory .
    DIRECTORY_SEPARATOR . "temp";

if (!is_dir($tempDirectory)) {
    if (
        !mkdir($tempDirectory, 0775, true) &&
        !is_dir($tempDirectory)
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
| Maximum permitted PDF size
|--------------------------------------------------------------------------
*/

$maxFileSize = defined("MAX_FILE_SIZE")
    ? (int)MAX_FILE_SIZE
    : 50 * 1024 * 1024;

if ($maxFileSize <= 0) {
    $maxFileSize = 50 * 1024 * 1024;
}


/*
|--------------------------------------------------------------------------
| Create temporary PDF
|--------------------------------------------------------------------------
*/

$tempFile = tempnam($tempDirectory, "onlyoffice_");

if ($tempFile === false) {
    jsonResponse(
        false,
        "Unable to create temporary PDF file.",
        [],
        500
    );
}

$fp = fopen($tempFile, "wb");

if ($fp === false) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "Unable to open temporary PDF file.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Download exported PDF from ONLYOFFICE
|--------------------------------------------------------------------------
*/

$downloadedBytes = 0;
$sizeLimitExceeded = false;

$curl = curl_init($fileUrl);

if ($curl === false) {
    fclose($fp);
    @unlink($tempFile);

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
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => "Communication-System-ONLYOFFICE/1.0",
        CURLOPT_FAILONERROR => false,
        CURLOPT_HEADER => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,

        CURLOPT_WRITEFUNCTION =>
            function ($curlHandle, $chunk) use (
                $fp,
                &$downloadedBytes,
                &$sizeLimitExceeded,
                $maxFileSize
            ) {
                $length = strlen($chunk);

                if ($downloadedBytes + $length > $maxFileSize) {
                    $sizeLimitExceeded = true;
                    return 0;
                }

                $written = fwrite($fp, $chunk);

                if ($written === false || $written !== $length) {
                    return 0;
                }

                $downloadedBytes += $written;

                return $written;
            }
    ]
);

$curlResult = curl_exec($curl);
$curlError = curl_error($curl);
$curlErrno = curl_errno($curl);

$httpCode = (int)curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);

$contentType = strtolower(
    (string)curl_getinfo(
        $curl,
        CURLINFO_CONTENT_TYPE
    )
);

curl_close($curl);
fclose($fp);


/*
|--------------------------------------------------------------------------
| Validate download result
|--------------------------------------------------------------------------
*/

if ($sizeLimitExceeded) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "The exported PDF exceeds the allowed file size.",
        [],
        413
    );
}

if ($curlResult === false) {
    @unlink($tempFile);

    error_log(
        "Generate signed copy: ONLYOFFICE download failed. " .
        "Document ID: " . $id .
        "; cURL error code: " . $curlErrno .
        "; Error: " . $curlError
    );

    jsonResponse(
        false,
        "Unable to download the PDF from ONLYOFFICE. Check the server connection and export URL.",
        [
            "curl_error_code" => $curlErrno
        ],
        502
    );
}

if ($httpCode < 200 || $httpCode >= 300) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "ONLYOFFICE returned HTTP status " . $httpCode .
        " while exporting the PDF.",
        [],
        502
    );
}

if (!is_file($tempFile)) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "The exported PDF was not downloaded.",
        [],
        502
    );
}

$fileSize = filesize($tempFile);

if ($fileSize === false || $fileSize <= 0) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "The exported PDF is empty.",
        [],
        422
    );
}

if ($fileSize > $maxFileSize) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "The exported PDF exceeds the allowed file size.",
        [],
        413
    );
}


/*
|--------------------------------------------------------------------------
| Verify PDF file signature
|--------------------------------------------------------------------------
*/

$fileHandle = fopen($tempFile, "rb");

if ($fileHandle === false) {
    @unlink($tempFile);

    jsonResponse(
        false,
        "Unable to verify the exported PDF.",
        [],
        500
    );
}

$pdfSignature = fread($fileHandle, 5);

fclose($fileHandle);

if ($pdfSignature !== "%PDF-") {
    @unlink($tempFile);

    jsonResponse(
        false,
        "ONLYOFFICE did not return a valid PDF file.",
        [
            "content_type" => $contentType
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Ensure permanent storage directory exists
|--------------------------------------------------------------------------
*/

if (!is_dir($documentsDirectory)) {
    if (
        !mkdir($documentsDirectory, 0775, true) &&
        !is_dir($documentsDirectory)
    ) {
        @unlink($tempFile);

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
| Generate a safe filename for the new main document
|--------------------------------------------------------------------------
*/

$originalName = trim(
    (string)($document["file_name"] ?? "")
);

if ($originalName === "") {
    $originalName = "document_" . $id;
}

$originalBaseName = pathinfo(
    $originalName,
    PATHINFO_FILENAME
);

$originalBaseName = preg_replace(
    '/[^A-Za-z0-9_-]+/',
    '_',
    $originalBaseName
);

$originalBaseName = trim($originalBaseName, "_-");

if ($originalBaseName === "") {
    $originalBaseName = "document_" . $id;
}

$finalFileName =
    $originalBaseName .
    "_signed_" .
    date("Ymd_His") .
    "_" .
    bin2hex(random_bytes(4)) .
    ".pdf";

$finalPath =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Save generated PDF
|--------------------------------------------------------------------------
*/

if (!rename($tempFile, $finalPath)) {
    if (!copy($tempFile, $finalPath)) {
        @unlink($tempFile);

        jsonResponse(
            false,
            "Unable to save the signed PDF.",
            [],
            500
        );
    }

    @unlink($tempFile);
}


/*
|--------------------------------------------------------------------------
| Verify saved PDF
|--------------------------------------------------------------------------
*/

if (
    !is_file($finalPath) ||
    filesize($finalPath) === false ||
    filesize($finalPath) <= 0
) {
    @unlink($finalPath);

    jsonResponse(
        false,
        "The signed PDF could not be verified after saving.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Relative path saved in the database
|--------------------------------------------------------------------------
*/

$relativeFilePath =
    "uploads/documents/" .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Make the generated PDF the main document
|--------------------------------------------------------------------------
|
| file_path:
|   The primary file used by the document list and download actions.
|
| file_name:
|   The name displayed by the application.
|
| reviewed_file:
|   Retained for compatibility with the existing approval workflow.
|
| The original uploaded file is not overwritten or deleted.
| The document status is not changed here.
|
*/

$updated = updateData(
    "documents",
    [
        "file_path" => $relativeFilePath,
        "file_name" => $finalFileName,
        "reviewed_file" => $relativeFilePath
    ],
    [
        "id" => $id
    ]
);

if (!$updated) {
    /*
     * Keep the generated PDF on disk for recovery.
     * The database still points to the previous document.
     */
    error_log(
        "Generate signed copy: database update failed for document ID " .
        $id .
        ". Generated file retained at: " .
        $finalPath
    );

    jsonResponse(
        false,
        "The signed PDF was generated, but the document record could not be updated. The generated file has been retained for recovery.",
        [
            "generated_file" => $relativeFilePath
        ],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    "The signed PDF has been saved as the main document successfully.",
    [
        "document_id" => $id,
        "file_path" => $relativeFilePath,
        "file_name" => $finalFileName,
        "reviewed_file" => $relativeFilePath,
        "reviewed_url" =>
            rtrim(APP_URL, "/") . "/" . $relativeFilePath,
        "generated_by" => $userId,
        "generated_at" => date("Y-m-d H:i:s")
    ]
);