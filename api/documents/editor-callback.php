
<?php

require_once __DIR__ . "/../../includes/config.php";

header("Content-Type: application/json; charset=UTF-8");

function callbackResponse(int $error): void
{
    echo json_encode(["error" => $error]);
    exit;
}

function callbackLog(string $message): void
{
    error_log("ONLYOFFICE callback: " . $message);
}

/*
|--------------------------------------------------------------------------
| Read ONLYOFFICE callback
|--------------------------------------------------------------------------
*/

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    callbackResponse(0);
}

$documentId = (int)(
    $_GET["id"] ??
    $data["document_id"] ??
    0
);

$status = (int)($data["status"] ?? 0);

/*
 * ONLYOFFICE statuses 2 and 6 contain a document URL to save.
 * Other statuses require only an acknowledgement.
 */
if (!in_array($status, [2, 6], true)) {
    callbackResponse(0);
}

if ($documentId <= 0 || empty($data["url"])) {
    callbackLog("Missing document ID or download URL.");
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Validate ONLYOFFICE download URL
|--------------------------------------------------------------------------
*/

$downloadUrl = trim((string)$data["url"]);
$urlParts = parse_url($downloadUrl);

$configuredDocsUrl = defined("ONLYOFFICE_DOCS_URL")
    ? trim((string)ONLYOFFICE_DOCS_URL)
    : "";

$docsParts = parse_url($configuredDocsUrl);

if (
    !$urlParts ||
    !$docsParts ||
    empty($urlParts["scheme"]) ||
    empty($urlParts["host"]) ||
    empty($docsParts["scheme"]) ||
    empty($docsParts["host"])
) {
    callbackLog("Invalid download URL or ONLYOFFICE configuration.");
    callbackResponse(1);
}

$urlScheme = strtolower($urlParts["scheme"]);
$docsScheme = strtolower($docsParts["scheme"]);

$urlHost = strtolower($urlParts["host"]);
$docsHost = strtolower($docsParts["host"]);

$urlPort = (int)(
    $urlParts["port"] ??
    ($urlScheme === "https" ? 443 : 80)
);

$docsPort = (int)(
    $docsParts["port"] ??
    ($docsScheme === "https" ? 443 : 80)
);

if (
    !in_array($urlScheme, ["http", "https"], true) ||
    $urlScheme !== $docsScheme ||
    $urlHost !== $docsHost ||
    $urlPort !== $docsPort ||
    !empty($urlParts["user"]) ||
    !empty($urlParts["pass"])
) {
    callbackLog("Rejected download URL outside configured ONLYOFFICE origin.");
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Load document
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "SELECT id, file_name, file_path
     FROM documents
     WHERE id = ?
       AND COALESCE(is_deleted, 0) = 0
     LIMIT 1",
    [$documentId]
);

if (!$document) {
    callbackLog("Document not found: " . $documentId);
    callbackResponse(1);
}

$fileName = basename(
    str_replace("\\", "/", (string)$document["file_name"])
);

$relativePath = str_replace(
    "\\",
    "/",
    trim((string)$document["file_path"])
);

$relativePath = ltrim($relativePath, "/");

/*
|--------------------------------------------------------------------------
| Validate stored path and filename
|--------------------------------------------------------------------------
*/

if (
    $fileName === "" ||
    $relativePath === "" ||
    strpos($relativePath, "../") !== false ||
    strpos($relativePath, "\0") !== false ||
    strpos($fileName, "\0") !== false ||
    !str_starts_with($relativePath, "uploads/documents/")
) {
    callbackLog("Unsafe or invalid stored document path: " . $documentId);
    callbackResponse(1);
}

$pathExtension = strtolower(
    pathinfo($relativePath, PATHINFO_EXTENSION)
);

$nameExtension = strtolower(
    pathinfo($fileName, PATHINFO_EXTENSION)
);

/*
 * Require filename and path to agree on the extension.
 * Never combine the path's directory with a different filename.
 */
if (
    $pathExtension === "" ||
    $nameExtension === "" ||
    $pathExtension !== $nameExtension
) {
    callbackLog(
        "Filename/path extension mismatch for document " . $documentId
    );
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Determine expected content format
|--------------------------------------------------------------------------
*/

$pdfExtensions = ["pdf"];

$zipOfficeExtensions = [
    "docx", "docm", "dotx", "dotm",
    "xlsx", "xlsm", "xltx", "xltm",
    "pptx", "pptm", "potx", "potm",
    "ppsx", "ppsm",
    "odt", "ods", "odp",
    "odg", "ott", "ots", "otp"
];

$oleOfficeExtensions = ["doc", "xls", "ppt", "dot", "xlt", "pot"];

$plainTextExtensions = ["txt", "rtf", "csv"];

$allowedExtensions = array_merge(
    $pdfExtensions,
    $zipOfficeExtensions,
    $oleOfficeExtensions,
    $plainTextExtensions
);

if (!in_array($pathExtension, $allowedExtensions, true)) {
    callbackLog(
        "Unsupported document extension ." . $pathExtension .
        " for document " . $documentId
    );
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Resolve the exact destination under uploads/documents
|--------------------------------------------------------------------------
*/

$appRoot = realpath(dirname(__DIR__, 2));

$documentsRoot = realpath(
    dirname(__DIR__, 2) .
    DIRECTORY_SEPARATOR . "uploads" .
    DIRECTORY_SEPARATOR . "documents"
);

if ($appRoot === false || $documentsRoot === false) {
    callbackLog("Application or documents directory does not exist.");
    callbackResponse(1);
}

$destination = $appRoot .
    DIRECTORY_SEPARATOR .
    str_replace("/", DIRECTORY_SEPARATOR, $relativePath);

$destinationDirectory = realpath(dirname($destination));

if (
    $destinationDirectory === false ||
    !is_file($destination) ||
    !is_writable($destinationDirectory)
) {
    callbackLog(
        "Destination missing or not writable for document " . $documentId
    );
    callbackResponse(1);
}

$normalizedRoot = rtrim(
    str_replace("\\", "/", $documentsRoot),
    "/"
);

$normalizedDirectory = str_replace(
    "\\",
    "/",
    $destinationDirectory
);

if (
    $normalizedDirectory !== $normalizedRoot &&
    !str_starts_with(
        $normalizedDirectory,
        $normalizedRoot . "/"
    )
) {
    callbackLog("Destination resolves outside documents storage.");
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Download ONLYOFFICE output
|--------------------------------------------------------------------------
*/

$ch = curl_init($downloadUrl);

if ($ch === false) {
    callbackLog("Could not initialize cURL.");
    callbackResponse(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_USERAGENT => "Communication-System-ONLYOFFICE/1.0"
]);

$fileContents = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if (
    $fileContents === false ||
    $httpCode < 200 ||
    $httpCode >= 300
) {
    callbackLog(
        "Download failed for document " . $documentId .
        "; HTTP " . $httpCode .
        "; " . $curlError
    );
    callbackResponse(1);
}

$contentLength = strlen($fileContents);

if ($contentLength <= 0 || $contentLength > 50 * 1024 * 1024) {
    callbackLog("Empty or oversized callback file for document " . $documentId);
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Verify downloaded bytes match the stored extension
|--------------------------------------------------------------------------
*/

$header = substr($fileContents, 0, 8);
$formatValid = false;

if (in_array($pathExtension, $pdfExtensions, true)) {
    $formatValid = str_starts_with($fileContents, "%PDF-");
} elseif (in_array($pathExtension, $zipOfficeExtensions, true)) {
    $formatValid = str_starts_with($fileContents, "PK\x03\x04");
} elseif (in_array($pathExtension, $oleOfficeExtensions, true)) {
    $formatValid = str_starts_with(
        $fileContents,
        "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"
    );
} elseif (in_array($pathExtension, $plainTextExtensions, true)) {
    // Text-based formats must not contain a PDF or ZIP file signature.
    $formatValid =
        !str_starts_with($fileContents, "%PDF-") &&
        !str_starts_with($fileContents, "PK\x03\x04") &&
        !str_starts_with(
            $fileContents,
            "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"
        );
}

if (!$formatValid) {
    callbackLog(
        "Rejected content/extension mismatch for document " .
        $documentId .
        "; extension ." . $pathExtension .
        "; header " . bin2hex($header)
    );
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Write to a temporary file, then atomically replace the destination
|--------------------------------------------------------------------------
*/

$tempPath = tempnam($destinationDirectory, ".oo_save_");

if ($tempPath === false) {
    callbackLog("Could not create temporary save file.");
    callbackResponse(1);
}

$written = file_put_contents($tempPath, $fileContents, LOCK_EX);

if ($written === false || $written !== $contentLength) {
    @unlink($tempPath);
    callbackLog("Failed writing temporary document file.");
    callbackResponse(1);
}

if (!rename($tempPath, $destination)) {
    @unlink($tempPath);
    callbackLog("Could not replace destination document.");
    callbackResponse(1);
}

/*
|--------------------------------------------------------------------------
| Keep MIME metadata consistent with the verified file format
|--------------------------------------------------------------------------
*/

$mimeTypes = [
    "pdf"  => "application/pdf",
    "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "docm" => "application/vnd.ms-word.document.macroEnabled.12",
    "doc"  => "application/msword",
    "dotx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
    "dotm" => "application/vnd.ms-word.template.macroEnabled.12",
    "dot"  => "application/msword",
    "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "xlsm" => "application/vnd.ms-excel.sheet.macroEnabled.12",
    "xls"  => "application/vnd.ms-excel",
    "xltx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.template",
    "xltm" => "application/vnd.ms-excel.template.macroEnabled.12",
    "xlt"  => "application/vnd.ms-excel",
    "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    "pptm" => "application/vnd.ms-powerpoint.presentation.macroEnabled.12",
    "ppt"  => "application/vnd.ms-powerpoint",
    "potx" => "application/vnd.openxmlformats-officedocument.presentationml.template",
    "potm" => "application/vnd.ms-powerpoint.template.macroEnabled.12",
    "pot"  => "application/vnd.ms-powerpoint",
    "ppsx" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
    "ppsm" => "application/vnd.ms-powerpoint.slideshow.macroEnabled.12",
    "txt"  => "text/plain",
    "rtf"  => "application/rtf",
    "csv"  => "text/csv",
    "odt"  => "application/vnd.oasis.opendocument.text",
    "ods"  => "application/vnd.oasis.opendocument.spreadsheet",
    "odp"  => "application/vnd.oasis.opendocument.presentation",
    "odg"  => "application/vnd.oasis.opendocument.graphics",
    "ott"  => "application/vnd.oasis.opendocument.text-template",
    "ots"  => "application/vnd.oasis.opendocument.spreadsheet-template",
    "otp"  => "application/vnd.oasis.opendocument.presentation-template"
];

$updated = updateData(
    "documents",
    [
        "file_type" => $mimeTypes[$pathExtension],
        "updated_at" => date("Y-m-d H:i:s")
    ],
    "id = ?",
    [$documentId]
);

if (!$updated) {
    callbackLog(
        "File saved but metadata update failed for document " . $documentId
    );
    callbackResponse(1);
}

callbackResponse(0);
