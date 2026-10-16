
    <?php

    require_once __DIR__ . "/includes/config.php";
    require_once __DIR__ . "/includes/auth.php";
    require_once __DIR__ . "/includes/ApprovalRouter.php";

    Auth::protect();

    $user = Auth::getCurrentUser();

    if (!$user || empty($user['id'])) {
        http_response_code(401);
        exit("Authentication required.");
    }

    $userId = (string)$user['id'];

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

    $draftId = (int)($_GET['draft_id'] ?? 0);
    $draftToken = trim((string)($_GET['draft_token'] ?? ''));
    $isDraftMode = $draftId > 0;

    /*
    |--------------------------------------------------------------------------
    | Load document or draft
    |--------------------------------------------------------------------------
    */

    if ($isDraftMode) {

        if (!preg_match('/^[a-f0-9]{64}$/i', $draftToken)) {
            http_response_code(403);
            exit("Invalid draft access token.");
        }

        $draft = fetchRow(
            "SELECT *
            FROM document_upload_drafts
            WHERE id = ?
            AND user_id = ?
            LIMIT 1",
            [$draftId, (int)$userId]
        );

        if (!$draft) {
            http_response_code(404);
            exit("Draft not found or access denied.");
        }

        if (!hash_equals(
            (string)$draft['draft_token'],
            $draftToken
        )) {
            http_response_code(403);
            exit("Invalid draft access token.");
        }

        if (($draft['status'] ?? '') !== 'draft') {
            http_response_code(409);
            exit("This draft is no longer available for signing.");
        }

        if (
            empty($draft['expires_at']) ||
            strtotime($draft['expires_at']) <= time()
        ) {
            http_response_code(410);
            exit("This draft has expired. Please upload the document again.");
        }

        $fileName = (string)$draft['original_file_name'];

        $document = [
            'id' => $draftId,
            'title' => $draft['title'] ?? $fileName,
            'file_name' => $fileName,
            'uploaded_by' => (int)$userId
        ];

    } else {

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            exit("Invalid document ID.");
        }

        $document = fetchRow(
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
            AND d.is_deleted = 0
            LIMIT 1",
            [$id]
        );

        if (!$document) {
            http_response_code(404);
            exit("Document not found.");
        }
    }
        

$fileName = (string)($document['file_name'] ?? '');

if ($fileName === '') {
    http_response_code(400);
    exit("Document file name is missing.");
}

/*
|--------------------------------------------------------------------------
| Supported document types
|--------------------------------------------------------------------------
*/

$fileType = strtolower(
    pathinfo($fileName, PATHINFO_EXTENSION)
);

$wordExtensions = [
    'doc', 'docx', 'docm', 'dot', 'dotx',
    'odt', 'rtf', 'txt'
];

$spreadsheetExtensions = [
    'xls', 'xlsx', 'xlsm', 'ods', 'csv'
];

$presentationExtensions = [
    'ppt', 'pptx', 'pptm', 'odp'
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
        "This document type is not supported in this editor mode: " .
        htmlspecialchars($fileType, ENT_QUOTES, 'UTF-8')
    );
}

/*
|--------------------------------------------------------------------------
| Plugin editor type
|--------------------------------------------------------------------------
*/

$pluginEditorType = $documentType;

    

    /*
    |--------------------------------------------------------------------------
    | Public application base URL
    |--------------------------------------------------------------------------
    |
    | This must be the public URL reachable by the browser and ONLYOFFICE.
    | For example:
    | https://your-current-tunnel.trycloudflare.com/communication
    |
    */

    $appBaseUrl = rtrim(ONLYOFFICE_PUBLIC_APP_URL, '/');

    if ($appBaseUrl === '') {
        http_response_code(500);
        exit("ONLYOFFICE_PUBLIC_APP_URL is not configured.");
    }

    /*
    |--------------------------------------------------------------------------
    | Document URL, callback URL, and editor key
    |--------------------------------------------------------------------------
    */

    if ($isDraftMode) {

        /*
        * ONLYOFFICE downloads the draft file using its access token.
        */
        $documentUrl =
            $appBaseUrl .
            '/api/documents/draft-file.php' .
            '?id=' . urlencode((string)$draftId) .
            '&token=' . urlencode($draftToken);

        /*
        * The draft callback must preserve the original uploaded file.
        * Verify that draft-callback.php writes to a working copy rather
        * than overwriting original_file_path.
        */
        $callbackUrl =
            $appBaseUrl .
            '/api/documents/draft-callback.php' .
            '?id=' . urlencode((string)$draftId) .
            '&token=' . urlencode($draftToken);

        $documentKey =
            'communication-draft-' .
            $draftId . '-' .
            time() . '-' .
            bin2hex(random_bytes(4));

    } else {

        $fileToken = hash_hmac(
            'sha256',
            (string)$id,
            ONLYOFFICE_JWT_SECRET
        );

        $documentUrl =
            $appBaseUrl .
            '/api/documents/editor-file.php' .
            '?id=' . urlencode((string)$id) .
            '&token=' . urlencode($fileToken);

        
$callbackUrl =
    $appBaseUrl .
    '/api/documents/editor-callback.php' .
    '?id=' . urlencode((string)$id);


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
    |
    | The plugin needs four values:
    |   mode      - draft or approval
    |   documentId - document ID or draft ID
    |   apiUrl    - authenticated signed-copy endpoint
    |   returnUrl - page to return to after saving the signed PDF
    |
    */

    if ($isDraftMode) {

        /*
        * HMAC for generate-draft-signed-copy.php.
        * Keep the payload identical to the validation in that endpoint.
        */
        $signedCopyPayload =
            $draftId . '|' .
            (int)$userId . '|' .
            $draftToken;

        $signedCopyToken = hash_hmac(
            'sha256',
            $signedCopyPayload,
            ONLYOFFICE_JWT_SECRET
        );

        $generateSignedCopyUrl =
            $appBaseUrl .
            '/api/documents/generate-draft-signed-copy.php' .
            '?draftId=' . urlencode((string)$draftId) .
            '&userId=' . urlencode($userId) .
            '&draftToken=' . urlencode($draftToken) .
            '&token=' . urlencode($signedCopyToken);

        /*
        * After signing a draft, return to Upload and include the
        * draft ID/token so upload.php can restore the existing draft.
        */
        $returnUrl =
            $appBaseUrl .
            '/upload.php' .
            '?draft_id=' . urlencode((string)$draftId) .
            '&draft_token=' . urlencode($draftToken);

        
$pluginOptions = [
    'mode' => 'draft',
    'draftId' => (string)$draftId,
    'draftToken' => $draftToken,
    'documentId' => (string)$draftId,
    'originalFileType' => $fileType,
    'apiUrl' => $generateSignedCopyUrl,
    'returnUrl' => $returnUrl,
    'editorType' => $pluginEditorType
];


    } else {

        /*
        * HMAC for generate-signed-copy.php.
        */
        $signedCopyPayload =
            $id . '|' . (int)$userId;

        $signedCopyToken = hash_hmac(
            'sha256',
            $signedCopyPayload,
            ONLYOFFICE_JWT_SECRET
        );

        $generateSignedCopyUrl =
            $appBaseUrl .
            '/api/documents/generate-signed-copy.php' .
            '?documentId=' . urlencode((string)$id) .
            '&userId=' . urlencode($userId) .
            '&token=' . urlencode($signedCopyToken);

        /*
        * After signing an approval document, return to its review page.
        */
        $returnUrl =
            $appBaseUrl .
            '/review-document.php' .
            '?id=' . urlencode((string)$id);

        
$pluginOptions = [
    'mode' => 'approval',
    'documentId' => (string)$id,
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
        ($user['first_name'] ?? '') . ' ' .
        ($user['last_name'] ?? '')
    );

    if ($userName === '') {
        $userName = trim((string)($user['username'] ?? ''));
    }

    if ($userName === '') {
        $userName = 'Communication User';
    }

    /*
    |--------------------------------------------------------------------------
    | ONLYOFFICE editor configuration
    |--------------------------------------------------------------------------
    */

    $documentTitle = (string)(
        $document['title'] ??
        $fileName
    );

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
        'asc.{8D6E9F31-7B54-4E1D-A2C8-9F5B6D734210}'
    ],

    'pluginsData' => [
        $appBaseUrl .
        '/plugins/document-signature/config.json'
    ],

    'options' => [
        'asc.{8D6E9F31-7B54-4E1D-A2C8-9F5B6D734210}' =>
            $pluginOptions
    ]
],


            'user' => [
                'id' => $userId,
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

    function createOnlyOfficeJwt(
        array $payload,
        string $secret
    ): string {
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
            throw new RuntimeException(
                'Unable to encode ONLYOFFICE JWT.'
            );
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
        ONLYOFFICE_JWT_SECRET
    );

    $pageTitle = htmlspecialchars(
        $documentTitle,
        ENT_QUOTES,
        'UTF-8'
    );

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

    <div id="editor-error"></div>
    <div id="editor-container"></div>

    <script
        type="text/javascript"
        src="<?= htmlspecialchars(
            rtrim(ONLYOFFICE_DOCS_URL, '/') .
            '/web-apps/apps/api/documents/api.js',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    ></script>

    <script>
    const editorConfig = <?= json_encode(
        $editorConfig,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    editorConfig.token = <?= json_encode($jwt) ?>;

    try {
        if (
            typeof DocsAPI === "undefined" ||
            typeof DocsAPI.DocEditor === "undefined"
        ) {
            throw new Error("ONLYOFFICE DocsAPI was not loaded.");
        }

        const docEditor = new DocsAPI.DocEditor(
            "editor-container",
            editorConfig
        );

    } catch (error) {
        console.error("ONLYOFFICE initialization error:", error);

        const errorBox = document.getElementById("editor-error");

        errorBox.style.display = "block";
        errorBox.textContent =
            "Unable to initialize the ONLYOFFICE editor.\n" +
            "Please check the browser console for details.";

        console.error("Editor configuration:", editorConfig);
    }           
    </script>

    </body>
    </html>