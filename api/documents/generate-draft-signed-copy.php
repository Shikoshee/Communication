
<?php

/*
|--------------------------------------------------------------------------
| Communication System
| Generate Signed Copy — Upload Drafts
|--------------------------------------------------------------------------
|
| Receives the PDF exported by ONLYOFFICE and saves it separately from
| the original Word document.
|
| Updates:
|   document_upload_drafts.signed_file_path
|
| Does NOT:
|   - overwrite original_file_path
|   - create a documents record
|   - create an approval record
|   - submit or approve the draft
|
*/

require_once __DIR__ . "/../../includes/config.php";


/*
|--------------------------------------------------------------------------
| JSON response
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=UTF-8");

function draftSignedResponse(
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

function draftSignedAddOrigin(array &$origins, string $url): void
{
    $parsed = parse_url(trim($url));

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

    if (!in_array($origin, $origins, true)) {
        $origins[] = $origin;
    }
}

$allowedOrigins = [];

if (defined("APP_URL")) {
    draftSignedAddOrigin($allowedOrigins, (string)APP_URL);
}

if (defined("ONLYOFFICE_PUBLIC_APP_URL")) {
    draftSignedAddOrigin(
        $allowedOrigins,
        (string)ONLYOFFICE_PUBLIC_APP_URL
    );
}

if (defined("ONLYOFFICE_DOCS_URL")) {
    draftSignedAddOrigin(
        $allowedOrigins,
        (string)ONLYOFFICE_DOCS_URL
    );
}

$requestOrigin = trim((string)($_SERVER["HTTP_ORIGIN"] ?? ""));

if ($requestOrigin !== "") {
    $normalizedOrigin = strtolower(rtrim($requestOrigin, "/"));

    if (!in_array($normalizedOrigin, $allowedOrigins, true)) {
        draftSignedResponse(
            false,
            "This request origin is not allowed.",
            [],
            403
        );
    }

    header("Access-Control-Allow-Origin: " . $requestOrigin);
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    header("Vary: Origin");
}

$method = strtoupper((string)($_SERVER["REQUEST_METHOD"] ?? ""));

if ($method === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($method !== "POST") {
    header("Allow: POST, OPTIONS");

    draftSignedResponse(
        false,
        "Only POST requests are allowed.",
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| Require configuration and cURL
|--------------------------------------------------------------------------
*/

if (
    !defined("ONLYOFFICE_JWT_SECRET") ||
    trim((string)ONLYOFFICE_JWT_SECRET) === ""
) {
    error_log(
        "Generate draft signed copy: ONLYOFFICE_JWT_SECRET is missing."
    );

    draftSignedResponse(
        false,
        "The document signing service is not configured.",
        [],
        500
    );
}

if (!function_exists("curl_init")) {
    error_log(
        "Generate draft signed copy: PHP cURL extension is unavailable."
    );

    draftSignedResponse(
        false,
        "The server's PHP cURL extension is not enabled.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Validate draft authentication parameters
|--------------------------------------------------------------------------
|
| Expected query parameters:
|   draftId
|   userId
|   draftToken
|   token
|
| Expected HMAC payload:
|   draftId|userId|draftToken
|
*/

$draftId = (int)($_GET["draftId"] ?? 0);
$userId = (int)($_GET["userId"] ?? 0);

$draftToken = trim((string)($_GET["draftToken"] ?? ""));
$hmacToken = trim((string)($_GET["token"] ?? ""));

if (
    $draftId <= 0 ||
    $userId <= 0 ||
    !preg_match('/^[a-f0-9]{64}$/i', $draftToken) ||
    !preg_match('/^[a-f0-9]{64}$/i', $hmacToken)
) {
    draftSignedResponse(
        false,
        "The draft authentication details are missing or invalid.",
        [],
        401
    );
}

$tokenPayload = $draftId . "|" . $userId . "|" . $draftToken;

$expectedToken = hash_hmac(
    "sha256",
    $tokenPayload,
    ONLYOFFICE_JWT_SECRET
);

if (!hash_equals($expectedToken, $hmacToken)) {
    draftSignedResponse(
        false,
        "Invalid draft authentication token.",
        [],
        401
    );
}


/*
|--------------------------------------------------------------------------
| Read request body
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    draftSignedResponse(
        false,
        "Invalid request data.",
        [],
        400
    );
}

$requestId = (int)($data["id"] ?? 0);

if ($requestId <= 0 || $requestId !== $draftId) {
    draftSignedResponse(
        false,
        "The requested draft ID does not match the authenticated draft.",
        [],
        403
    );
}

$fileUrl = trim((string)($data["file_url"] ?? ""));

if ($fileUrl === "") {
    draftSignedResponse(
        false,
        "The exported PDF URL is missing.",
        [],
        400
    );
}


/*
|--------------------------------------------------------------------------
| Validate ONLYOFFICE export URL
|--------------------------------------------------------------------------
*/

$parsedUrl = parse_url($fileUrl);

if (
    !$parsedUrl ||
    strtolower((string)($parsedUrl["scheme"] ?? "")) !== "https" ||
    empty($parsedUrl["host"]) ||
    !empty($parsedUrl["user"]) ||
    !empty($parsedUrl["pass"])
) {
    draftSignedResponse(
        false,
        "The exported PDF URL is invalid. HTTPS is required.",
        [],
        400
    );
}

$onlyofficeHost = "";

if (defined("ONLYOFFICE_DOCS_URL")) {
    $onlyofficeParsed = parse_url((string)ONLYOFFICE_DOCS_URL);

    if ($onlyofficeParsed && !empty($onlyofficeParsed["host"])) {
        $onlyofficeHost = strtolower($onlyofficeParsed["host"]);
    }
}

$requestHost = strtolower((string)$parsedUrl["host"]);

if ($onlyofficeHost === "" || $requestHost !== $onlyofficeHost) {
    draftSignedResponse(
        false,
        "The export URL does not match the configured ONLYOFFICE server.",
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Load draft and verify ownership, token, and expiry
|--------------------------------------------------------------------------
*/

$draft = fetchRow(
    "
    SELECT *
    FROM document_upload_drafts
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
    ",
    [$draftId, $userId]
);

if (!$draft) {
    draftSignedResponse(
        false,
        "The upload draft was not found or does not belong to this user.",
        [],
        404
    );
}

$storedDraftToken = (string)($draft["draft_token"] ?? "");

if (
    $storedDraftToken === "" ||
    !hash_equals($storedDraftToken, $draftToken)
) {
    draftSignedResponse(
        false,
        "The draft access token is invalid.",
        [],
        403
    );
}

if (strtolower((string)($draft["status"] ?? "")) !== "draft") {
    draftSignedResponse(
        false,
        "This draft is no longer available for editing.",
        [],
        409
    );
}

$expiry = strtotime((string)($draft["expires_at"] ?? ""));

if ($expiry === false || $expiry <= time()) {
    draftSignedResponse(
        false,
        "This draft has expired. Please create a new upload draft.",
        [],
        410
    );
}


/*
|--------------------------------------------------------------------------
| Validate temporary storage directory
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
    if (!mkdir($tempDirectory, 0775, true) && !is_dir($tempDirectory)) {
        draftSignedResponse(
            false,
            "Unable to create the draft storage directory.",
            [],
            500
        );
    }
}


/*
|--------------------------------------------------------------------------
| File size limit
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
| Download exported PDF into a temporary file
|--------------------------------------------------------------------------
*/

$tempFile = tempnam($tempDirectory, "draft_pdf_");

if ($tempFile === false) {
    draftSignedResponse(
        false,
        "Unable to create a temporary PDF file.",
        [],
        500
    );
}

$fp = fopen($tempFile, "wb");

if ($fp === false) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "Unable to open the temporary PDF file.",
        [],
        500
    );
}

$downloadedBytes = 0;
$sizeLimitExceeded = false;
$writeFailed = false;

$curl = curl_init($fileUrl);

if ($curl === false) {
    fclose($fp);
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "Unable to initialize the PDF download.",
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
        CURLOPT_USERAGENT => "Communication-System-Draft-Signing/1.0",
        CURLOPT_FAILONERROR => false,
        CURLOPT_HEADER => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,

        CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (
            $fp,
            &$downloadedBytes,
            &$sizeLimitExceeded,
            &$writeFailed,
            $maxFileSize
        ) {
            $length = strlen($chunk);

            if ($downloadedBytes + $length > $maxFileSize) {
                $sizeLimitExceeded = true;
                return 0;
            }

            $written = fwrite($fp, $chunk);

            if ($written === false || $written !== $length) {
                $writeFailed = true;
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
$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$contentType = strtolower(
    (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE)
);

curl_close($curl);
fclose($fp);

if ($sizeLimitExceeded) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "The exported PDF exceeds the allowed file size.",
        [],
        413
    );
}

if ($writeFailed) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "The server could not write the downloaded PDF.",
        [],
        500
    );
}

if ($curlResult === false) {
    @unlink($tempFile);

    error_log(
        "Generate draft signed copy: download failed. " .
        "Draft ID: " . $draftId .
        "; cURL code: " . $curlErrno .
        "; error: " . $curlError
    );

    draftSignedResponse(
        false,
        "Unable to download the exported PDF from ONLYOFFICE.",
        ["curl_error_code" => $curlErrno],
        502
    );
}

if ($httpCode < 200 || $httpCode >= 300) {
    @unlink($tempFile);

    error_log(
        "Generate draft signed copy: ONLYOFFICE returned HTTP " .
        $httpCode . " for draft " . $draftId
    );

    draftSignedResponse(
        false,
        "ONLYOFFICE returned HTTP status " . $httpCode . " while exporting the PDF.",
        [],
        502
    );
}

$fileSize = filesize($tempFile);

if ($fileSize === false || $fileSize <= 0) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "The exported PDF is empty.",
        [],
        422
    );
}

if ($fileSize > $maxFileSize) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "The exported PDF exceeds the allowed file size.",
        [],
        413
    );
}


/*
|--------------------------------------------------------------------------
| Verify PDF signature
|--------------------------------------------------------------------------
*/

$handle = fopen($tempFile, "rb");

if ($handle === false) {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "Unable to verify the exported PDF.",
        [],
        500
    );
}

$pdfSignature = fread($handle, 5);
fclose($handle);

if ($pdfSignature !== "%PDF-") {
    @unlink($tempFile);

    draftSignedResponse(
        false,
        "ONLYOFFICE did not return a valid PDF file.",
        ["content_type" => $contentType],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Save PDF as a separate draft signed file
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We do not write to or rename over original_file_path.
*/

$signedFileName =
    "draft_" . $draftId .
    "_signed_" . date("Ymd_His") .
    "_" . bin2hex(random_bytes(6)) .
    ".pdf";

$finalPath =
    $tempDirectory .
    DIRECTORY_SEPARATOR .
    $signedFileName;

if (!rename($tempFile, $finalPath)) {
    if (!copy($tempFile, $finalPath)) {
        @unlink($tempFile);

        draftSignedResponse(
            false,
            "Unable to save the signed PDF.",
            [],
            500
        );
    }

    @unlink($tempFile);
}

if (
    !is_file($finalPath) ||
    filesize($finalPath) === false ||
    filesize($finalPath) <= 0
) {
    @unlink($finalPath);

    draftSignedResponse(
        false,
        "The saved PDF could not be verified.",
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Store relative path in draft record
|--------------------------------------------------------------------------
*/

$relativePath =
    "uploads/documents/temp/" . $signedFileName;

$updated = updateData(
    "document_upload_drafts",
    [
        "signed_file_path" => $relativePath
    ],
    [
        "id" => $draftId,
        "user_id" => $userId,
        "status" => "draft"
    ]
);

if (!$updated) {
    /*
     * Keep the generated PDF for diagnosis/recovery.
     * Do not change the original Word file.
     */
    error_log(
        "Generate draft signed copy: database update failed. " .
        "Draft ID: " . $draftId .
        "; generated file: " . $finalPath
    );

    draftSignedResponse(
        false,
        "The PDF was generated, but the draft record could not be updated.",
        ["generated_file" => $relativePath],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

draftSignedResponse(
    true,
    "The signed PDF has been saved to the upload draft. The original Word document remains unchanged.",
    [
        "draft_id" => $draftId,
        "signed_file_path" => $relativePath,
        "file_name" => $signedFileName,
        "file_size" => filesize($finalPath),
        "generated_at" => date("Y-m-d H:i:s")
    ]
);