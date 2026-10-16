<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

$id = (int)($_GET['documentId'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Invalid document ID'
    ]);
    exit;
}

$appBaseUrl = rtrim(
    ONLYOFFICE_PUBLIC_APP_URL,
    '/'
);

$apiUrl =
    $appBaseUrl .
    '/api/documents/generate-signed-copy.php';

$pluginBaseUrl =
    $appBaseUrl .
    '/plugins/document-signature/';

$config = [

    'name' =>
        'Document Signature',

    'guid' =>
        'asc.{8D6E9F31-7B54-4E1D-A2C8-9F5B6D734210}',

    'version' =>
        '1.0.0',

    'baseUrl' =>
        $pluginBaseUrl,

    'minVersion' =>
        '8.1.0',

    'variations' => [

        [

            'description' =>
                'Communication System Document Signature',

            'url' =>
                'index.html' .
                '?documentId=' .
                rawurlencode((string)$id) .
                '&apiUrl=' .
                rawurlencode($apiUrl),

            'type' =>
                'panelRight',

            'isViewer' =>
                false,

            'isDisplayedInViewer' =>
                false,

            'EditorsSupport' => [
                'word'
            ],

            'initDataType' =>
                'none',

            'initData' =>
                '',

            'buttons' => []

        ]

    ]

];

header(
    'Content-Type: application/json; charset=utf-8'
);

echo json_encode(
    $config,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE
);