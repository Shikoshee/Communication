
<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ApprovalRouter.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user || empty($user['id'])) {
    http_response_code(401);
    exit('Authentication required.');
}

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Determine editor mode
|--------------------------------------------------------------------------
|
| Normal document:
|   document-editor.php?id=DOCUMENT_ID
|
| Draft document:
|   document-editor.php?draft_id=DRAFT_ID&draft_token=DRAFT_TOKEN
|
*/

$draftId = (int) ($_GET['draft_id'] ?? 0);
$draftToken = trim((string) ($_GET['draft_token'] ?? ''));
$isDraftMode = $draftId > 0;

$id = 0;
$draft = null;
$document = null;
$fileName = '';

/*
|--------------------------------------------------------------------------
| Load document or upload draft
|--------------------------------------------------------------------------
*/

if ($isDraftMode) {

    if (!preg_match('/^[a-f0-9]{64}$/i', $draftToken)) {
        http_response_code(403);
        exit('Invalid draft access token.');
    }

    $draft = fetchRow(
        "SELECT *
         FROM document_upload_drafts
         WHERE id = ?
           AND user_id = ?
         LIMIT 1",
        [$draftId, $userId]
    );

    if (!$draft) {
        http_response_code(404);
        exit('Draft not found or access denied.');
    }

    if (!hash_equals(
        (string) ($draft['draft_token'] ?? ''),
        $draftToken
    )) {
        http_response_code(403);
        exit('Invalid draft access token.');
    }

    if (strtolower((string) ($draft['status'] ?? '')) !== 'draft') {
        http_response_code(409);
        exit('This draft is no longer available for signing.');
    }

    $expiry = strtotime((string) ($draft['expires_at'] ?? ''));

    if ($expiry === false || $expiry <= time()) {
        http_response_code(410);
        exit('This draft has expired. Please upload the document again.');
    }

    $fileName = trim((string) ($draft['original_file_name'] ?? ''));

    $document = [
        'id' => $draftId,
        'title' => $draft['title'] ?? $fileName,
        'file_name' => $fileName,
        'uploaded_by' => $userId
    ];

} else {

    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        exit('Invalid document ID.');
    }

    $document = fetchRow(
        "SELECT
            d.*,
            CONCAT(u.first_name, ' ', u.last_name) AS uploader
         FROM documents d
         LEFT JOIN users u ON u.id = d.uploaded_by
         WHERE d.id = ?
           AND d.is_deleted = 0
         LIMIT 1",
        [$id]
    );

    if (!$document) {
        http_response_code(404);
        exit('Document not found.');
    }

    $fileName = trim((string) ($document['file_name'] ?? ''));
}

if ($fileName === '') {
    http_response_code(400);
    exit('Document file name is missing.');
}

/*
|--------------------------------------------------------------------------
| Supported document types
|--------------------------------------------------------------------------
*/

$fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$wordExtensions = [
    'doc',
    'docx',
    'docm',
    'dot',
    'dotx',
    'odt',
    'rtf',
    'txt'
];

$spreadsheetExtensions = [
    'xls',
    'xlsx',
    'xlsm',
    'ods',
    'csv'
];

$presentationExtensions = [
    'ppt',
    'pptx',
    'pptm',
    'odp'
];

$pdfExtensions = ['pdf'];

if (in_array($fileType, $wordExtensions, true)) {

    $documentType = 'word';

} elseif (
    !$isDraftMode &&
    in_array($fileType, $spreadsheetExtensions, true)
) {

    $documentType = 'cell';

} elseif (
    !$isDraftMode &&
    in_array($fileType, $presentationExtensions, true)
) {

    $documentType = 'slide';

} elseif (
    !$isDraftMode &&
    in_array($fileType, $pdfExtensions, true)
) {

    $documentType = 'pdf';

} else {

    http_response_code(415);

    exit(
        'This document type is not supported in this editor mode: ' .
        htmlspecialchars($fileType, ENT_QUOTES, 'UTF-8')
    );
}

$pluginEditorType = $documentType;

/*
|--------------------------------------------------------------------------
| Configure application URLs
|--------------------------------------------------------------------------
|
| $appBaseUrl:
|   Public HTTPS application URL used by ONLYOFFICE for document
|   downloads, callbacks, plugin loading and signed-copy operations.
|
| $browserAppBaseUrl:
|   Application URL matching the current browser request. This
|   preserves access through the office computer's LAN IP address.
|
*/

$appBaseUrl = rtrim((string) ONLYOFFICE_PUBLIC_APP_URL, '/');

if ($appBaseUrl === '') {
    http_response_code(500);
    exit('ONLYOFFICE_PUBLIC_APP_URL is not configured.');
}

$docsBaseUrl = rtrim((string) ONLYOFFICE_DOCS_URL, '/');

if ($docsBaseUrl === '') {
    http_response_code(500);
    exit('ONLYOFFICE_DOCS_URL is not configured.');
}

if (
    !defined('ONLYOFFICE_JWT_SECRET') ||
    (string) ONLYOFFICE_JWT_SECRET === ''
) {
    http_response_code(500);
    exit('ONLYOFFICE_JWT_SECRET is not configured.');
}

/*
 * Build the browser-facing base URL from the current request.
 * Do not use this URL for ONLYOFFICE document downloads or callbacks.
 */

$browserHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

$scriptName = str_replace(
    '\\',
    '/',
    (string) ($_SERVER['SCRIPT_NAME'] ?? '/communication/document-editor.php')
);

$browserScheme = 'http';

if (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
) {
    $browserScheme = 'https';
}

$browserAppPath = rtrim(
    str_replace('\\', '/', dirname($scriptName)),
    '/'
);

if ($browserAppPath === '.' || $browserAppPath === '/') {
    $browserAppPath = '';
}

if ($browserHost !== '') {

    $browserAppBaseUrl =
        $browserScheme . '://' . $browserHost . $browserAppPath;

} else {

    // Fallback if the web server does not supply HTTP_HOST.
    $browserAppBaseUrl = $appBaseUrl;
}

/*
|--------------------------------------------------------------------------
| Document URL, callback URL and editor key
|--------------------------------------------------------------------------
*/

if ($isDraftMode) {

    // ONLYOFFICE downloads the original draft.
    $documentUrl =
        $appBaseUrl .
        '/api/documents/draft-file.php' .
        '?id=' . urlencode((string) $draftId) .
        '&token=' . urlencode($draftToken);

    // Draft callback must not overwrite the original document.
    $callbackUrl =
        $appBaseUrl .
        '/api/documents/draft-callback.php' .
        '?id=' . urlencode((string) $draftId) .
        '&token=' . urlencode($draftToken);

    $documentKey =
        'communication-draft-' .
        $draftId . '-' .
        time() . '-' .
        bin2hex(random_bytes(4));

} else {

    $fileToken = hash_hmac(
        'sha256',
        (string) $id,
        ONLYOFFICE_JWT_SECRET
    );

    $documentUrl =
        $appBaseUrl .
        '/api/documents/editor-file.php' .
        '?id=' . urlencode((string) $id) .
        '&token=' . urlencode($fileToken);

    $callbackUrl =
        $appBaseUrl .
        '/api/documents/editor-callback.php' .
        '?id=' . urlencode((string) $id);

    $documentKey =
        'communication-' .
        $id . '-' .
        time() . '-' .
        bin2hex(random_bytes(4));
}

/*
|--------------------------------------------------------------------------
| Signature plugin configuration
|--------------------------------------------------------------------------
*/

if ($isDraftMode) {

    $signedCopyPayload =
        $draftId . '|' .
        $userId . '|' .
        $draftToken;

    $signedCopyToken = hash_hmac(
        'sha256',
        $signedCopyPayload,
        ONLYOFFICE_JWT_SECRET
    );

    // Public endpoint used by the signature plugin.
    $generateSignedCopyUrl =
        $appBaseUrl .
        '/api/documents/generate-draft-signed-copy.php' .
        '?draftId=' . urlencode((string) $draftId) .
        '&userId=' . urlencode((string) $userId) .
        '&draftToken=' . urlencode($draftToken) .
        '&token=' . urlencode($signedCopyToken);

    /*
     * CHANGE: Use the public HTTPS URL for the signing return URL.
     * Keep $browserAppBaseUrl unchanged for browser-facing behavior.
     */
    $returnUrl =
        $appBaseUrl .
        '/upload.php' .
        '?draft_id=' . urlencode((string) $draftId) .
        '&draft_token=' . urlencode($draftToken);

    $pluginOptions = [
        'mode' => 'draft',
        'draftId' => (string) $draftId,
        'draftToken' => $draftToken,
        'documentId' => (string) $draftId,
        'originalFileType' => $fileType,
        'apiUrl' => $generateSignedCopyUrl,
        'returnUrl' => $returnUrl,
        'editorType' => $pluginEditorType
    ];

} else {

    $signedCopyPayload = $id . '|' . $userId;

    $signedCopyToken = hash_hmac(
        'sha256',
        $signedCopyPayload,
        ONLYOFFICE_JWT_SECRET
    );

    $generateSignedCopyUrl =
        $appBaseUrl .
        '/api/documents/generate-signed-copy.php' .
        '?documentId=' . urlencode((string) $id) .
        '&userId=' . urlencode((string) $userId) .
        '&token=' . urlencode($signedCopyToken);

    /*
     * CHANGE: Use the public HTTPS URL for the signing return URL.
     * Do not replace $browserAppBaseUrl globally.
     */
    /*
 * Browser navigation must use the same host the user
 * used to open the Communication System.
 *
 * Keep $appBaseUrl for ONLYOFFICE downloads, callbacks
 * and the signed-copy API.
 */
$returnUrl =
    $browserAppBaseUrl .
    '/review-document.php' .
    '?id=' . urlencode((string) $id);

    $pluginOptions = [
        'mode' => 'approval',
        'documentId' => (string) $id,
        'originalFileType' => $fileType,
        'apiUrl' => $generateSignedCopyUrl,
        'returnUrl' => $returnUrl,
        'editorType' => $pluginEditorType
    ];
}

/*
|--------------------------------------------------------------------------
| User information
|--------------------------------------------------------------------------
*/

$userName = trim(
    (string) ($user['first_name'] ?? '') . ' ' .
    (string) ($user['last_name'] ?? '')
);

if ($userName === '') {
    $userName = trim((string) ($user['username'] ?? ''));
}

if ($userName === '') {
    $userName = 'Communication User';
}

/*
|--------------------------------------------------------------------------
| ONLYOFFICE editor configuration
|--------------------------------------------------------------------------
*/

$documentTitle = (string) ($document['title'] ?? $fileName);

$pluginGuid = 'asc.{8D6E9F31-7B54-4E1D-A2C8-9F5B6D734210}';

$editorConfig = [
    'documentType' => $documentType,
    'type' => 'desktop',
    'height' => '100%',
    'width' => '100%',

    'document' => [
        'fileType' => $fileType,
        'key' => $documentKey,
        'title' => $documentTitle,
        'url' => $documentUrl,

        'permissions' => [
            'edit' => true,
            'download' => true,
            'print' => true,
            'comment' => true,
            'review' => true
        ]
    ],

    'editorConfig' => [
        'mode' => 'edit',
        'lang' => 'en',
        'callbackUrl' => $callbackUrl,

        'plugins' => [
            'autostart' => [
                $pluginGuid
            ],

            'pluginsData' => [
                $appBaseUrl . '/plugins/document-signature/config.json'
            ],

            'options' => [
                $pluginGuid => $pluginOptions
            ]
        ],

        'user' => [
            'id' => (string) $userId,
            'name' => $userName
        ],

        'customization' => [
            'autosave' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'hideRightMenu' => false,
            'toolbarNoTabs' => false
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| ONLYOFFICE JWT helpers
|--------------------------------------------------------------------------
*/

function base64UrlEncode(string $data): string
{
    return rtrim(
        strtr(base64_encode($data), '+/', '-_'),
        '='
    );
}

function createOnlyOfficeJwt(array $payload, string $secret): string
{
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $headerJson = json_encode(
        $header,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($headerJson === false || $payloadJson === false) {
        throw new RuntimeException('Unable to encode ONLYOFFICE JWT.');
    }

    $encodedHeader = base64UrlEncode($headerJson);
    $encodedPayload = base64UrlEncode($payloadJson);

    $signature = hash_hmac(
        'sha256',
        $encodedHeader . '.' . $encodedPayload,
        $secret,
        true
    );

    return
        $encodedHeader . '.' .
        $encodedPayload . '.' .
        base64UrlEncode($signature);
}

$jwt = createOnlyOfficeJwt(
    $editorConfig,
    (string) ONLYOFFICE_JWT_SECRET
);

$pageTitle = htmlspecialchars(
    $documentTitle,
    ENT_QUOTES,
    'UTF-8'
);

$apiScriptUrl =
    $docsBaseUrl . '/web-apps/apps/api/documents/api.js';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= $pageTitle ?> - Document Editor</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
        }

        #editor-container {
            width: 100%;
            height: 100vh;
            min-height: 600px;
        }

        #editor-error {
            display: none;
            padding: 30px;
            font-size: 16px;
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            margin: 30px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>

<div id="editor-error" role="alert"></div>
<div id="editor-container"></div>

<script
    type="text/javascript"
    src="<?= htmlspecialchars($apiScriptUrl, ENT_QUOTES, 'UTF-8') ?>"
></script>

<script>
"use strict";

const editorConfig = <?= json_encode(
    $editorConfig,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

editorConfig.token = <?= json_encode(
    $jwt,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

/*
|--------------------------------------------------------------------------
| Initialize ONLYOFFICE
|--------------------------------------------------------------------------
*/

try {
    if (
        typeof DocsAPI === "undefined" ||
        typeof DocsAPI.DocEditor !== "function"
    ) {
        throw new Error("ONLYOFFICE DocsAPI was not loaded.");
    }

    window.docEditor = new DocsAPI.DocEditor(
        "editor-container",
        editorConfig
    );

} catch (error) {
    console.error("ONLYOFFICE initialization error:", error);

    const errorBox = document.getElementById("editor-error");

    errorBox.style.display = "block";
    errorBox.textContent =
        "Unable to initialize the ONLYOFFICE editor.\n" +
        "Please check the browser console and ONLYOFFICE server.";

    console.error("Editor configuration:", editorConfig);
}

/*
|--------------------------------------------------------------------------
| Signed-copy return listener
|--------------------------------------------------------------------------
*/

const expectedSigningReturnUrl = <?= json_encode(
    $returnUrl,
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

(function () {
    "use strict";

    let expectedUrl;

    try {
        expectedUrl = new URL(expectedSigningReturnUrl);
    } catch (error) {
        console.error(
            "[SIGN RETURN] Invalid configured return URL.",
            error
        );
        return;
    }

    window.addEventListener("message", function (event) {
        let message = event.data;

        if (typeof message === "string") {
            try {
                message = JSON.parse(message);
            } catch (error) {
                return;
            }
        }

        if (
            !message ||
            typeof message !== "object" ||
            message.type !== "COMMUNICATION_SIGNED_COPY_RETURN" ||
            typeof message.url !== "string"
        ) {
            return;
        }

        console.log("[SIGN RETURN] Received return request:", {
            originReceived: event.origin,
            originExpected: expectedUrl.origin,
            urlReceived: message.url,
            urlExpected: expectedUrl.href
        });

        /*
         * Validate the requested destination.
         * The plugin's message origin can be the ONLYOFFICE origin,
         * so do not incorrectly require it to equal the app origin.
         */

        let requestedUrl;

        try {
            requestedUrl = new URL(message.url);
        } catch (error) {
            console.error("[SIGN RETURN] Invalid requested URL.");
            return;
        }

        if (requestedUrl.href !== expectedUrl.href) {
            console.error(
                "[SIGN RETURN] Rejected unexpected return URL:",
                requestedUrl.href
            );
            return;
        }

        const isDraftReturn =
            expectedUrl.pathname.toLowerCase().endsWith("/upload.php") &&
            expectedUrl.searchParams.has("draft_id") &&
            expectedUrl.searchParams.has("draft_token");

        const isApprovalReturn =
            expectedUrl.pathname.toLowerCase().endsWith("/review-document.php") &&
            expectedUrl.searchParams.has("id");

        if (!isDraftReturn && !isApprovalReturn) {
            console.error("[SIGN RETURN] Unsupported return destination.");
            return;
        }

        /*
         * Navigate only after checking the exact destination
         * generated by PHP.
         */

        console.log("[SIGN RETURN] Navigating to:", expectedUrl.href);
        window.location.assign(expectedUrl.href);
    });
})();

/*
|--------------------------------------------------------------------------
| Signing fallback: poll for a saved signed PDF
|--------------------------------------------------------------------------
*/

<?php if ($isDraftMode || $id > 0): ?>

(function () {
    "use strict";

    const isDraftMode = <?= $isDraftMode ? 'true' : 'false' ?>;

    const statusUrl = new URL(
        isDraftMode
            ? "api/documents/draft-signing-status.php"
            : "api/documents/signing-status.php",
        window.location.href
    );

    if (isDraftMode) {
        statusUrl.searchParams.set(
            "draft_id",
            <?= json_encode((string) $draftId) ?>
        );

        statusUrl.searchParams.set(
            "draft_token",
            <?= json_encode($draftToken) ?>
        );
    } else {
        statusUrl.searchParams.set(
            "document_id",
            <?= json_encode((string) $id) ?>
        );
    }

    const returnUrl = isDraftMode
        ? new URL("upload.php", window.location.href)
        : new URL("review-document.php", window.location.href);

    <?php if ($isDraftMode): ?>

    returnUrl.searchParams.set(
        "draft_id",
        <?= json_encode((string) $draftId) ?>
    );

    returnUrl.searchParams.set(
        "draft_token",
        <?= json_encode($draftToken) ?>
    );

    <?php else: ?>

    returnUrl.searchParams.set(
        "id",
        <?= json_encode((string) $id) ?>
    );

    <?php endif; ?>

    let checking = false;
    let returned = false;

    async function checkSignedCopy() {
        if (checking || returned) {
            return;
        }

        checking = true;

        try {
            const response = await fetch(statusUrl.href, {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            });

            if (!response.ok) {
                console.warn(
                    "[SIGN RETURN] Status request returned HTTP",
                    response.status
                );
                return;
            }

            const result = await response.json();

            if (
                result.success === true &&
                result.signed === true
            ) {
                returned = true;

                console.log(
                    "[SIGN RETURN] Signed copy confirmed. Returning to:",
                    returnUrl.href
                );

                window.location.replace(returnUrl.href);
            }

        } catch (error) {
            console.warn(
                "[SIGN RETURN] Status check failed; will retry.",
                error
            );

        } finally {
            checking = false;
        }
    }

    console.log("[SIGN RETURN] Signed-copy polling enabled.");
    console.log("[SIGN RETURN] Status endpoint:", statusUrl.href);

    checkSignedCopy();
    window.setInterval(checkSignedCopy, 2000);
})();

<?php endif; ?>

</script>

</body>
</html>
