<?php

require_once "includes/config.php";
require_once "includes/auth.php";

Auth::protect();

function base64UrlEncode(string $data): string
{
    return rtrim(
        strtr(
            base64_encode($data),
            '+/',
            '-_'
        ),
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

    $encodedHeader = base64UrlEncode(
        json_encode(
            $header,
            JSON_UNESCAPED_SLASHES
        )
    );

    $encodedPayload = base64UrlEncode(
        json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        )
    );

    $signature = hash_hmac(
        'sha256',
        $encodedHeader . '.' . $encodedPayload,
        $secret,
        true
    );

    return
        $encodedHeader .
        '.' .
        $encodedPayload .
        '.' .
        base64UrlEncode($signature);
}


/*
|--------------------------------------------------------------------------
| Use ONLYOFFICE's public demo document
|--------------------------------------------------------------------------
*/

$config = [

    'documentType' => 'word',

    'document' => [

        'fileType' => 'docx',

        'key' => 'communication-test-' . time(),

        'title' => 'ONLYOFFICE Test Document.docx',

        'url' =>
            'https://static.onlyoffice.com/assets/docs/samples/demo.docx',

    ],

];


$jwt = createOnlyOfficeJwt(
    $config,
    ONLYOFFICE_JWT_SECRET
);

$config['token'] = $jwt;

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>ONLYOFFICE Test</title>

    <style>

        html,
        body {

            margin: 0;

            padding: 0;

            width: 100%;

            height: 100%;

            overflow: hidden;

        }

        #placeholder {

            width: 100%;

            height: 100vh;

        }

    </style>

</head>

<body>

<div id="placeholder"></div>

<script
    type="text/javascript"
    src="<?= htmlspecialchars(
        rtrim(ONLYOFFICE_DOCS_URL, '/') .
        '/web-apps/apps/api/documents/api.js'
    ) ?>"
></script>

<script>

const config =
    <?= json_encode(
        $config,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;

console.log(
    "ONLYOFFICE TEST CONFIG:",
    config
);

try {

    const docEditor =
        new DocsAPI.DocEditor(
            "placeholder",
            config
        );

} catch (error) {

    console.error(
        "ONLYOFFICE TEST ERROR:",
        error
    );

}

</script>

</body>

</html>