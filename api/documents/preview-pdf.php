<?php

/**
 * Document PDF Preview Endpoint
 *
 * Purpose:
 * - Load an existing document
 * - Stream PDF files directly
 * - Convert DOC/DOCX to a persistent cached PDF
 * - The cached PDF is also used by sign.php
 * - Return ONLY PDF data to PDF.js
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/ApprovalRouter.php";

Auth::protect();

$user = Auth::getCurrentUser();


/*
|--------------------------------------------------------------------------
| Error response
|--------------------------------------------------------------------------
*/

function previewError($message, $statusCode = 400)
{
    http_response_code($statusCode);

    header('Content-Type: text/plain; charset=UTF-8');

    echo $message;

    exit;
}


/*
|--------------------------------------------------------------------------
| GET only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    previewError(
        'Invalid request method.',
        405
    );

}


/*
|--------------------------------------------------------------------------
| Document ID
|--------------------------------------------------------------------------
*/

$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {

    previewError(
        'Invalid document ID.',
        400
    );

}


/*
|--------------------------------------------------------------------------
| Get document
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "
    SELECT *
    FROM documents
    WHERE id = ?
    ",
    [$documentId]
);

if (!$document) {

    previewError(
        'Document not found.',
        404
    );

}


/*
|--------------------------------------------------------------------------
| Permission check
|--------------------------------------------------------------------------
*/

if (
    !ApprovalRouter::canUserApproveDocument(
        (int)$user['id'],
        $documentId
    )
) {

    previewError(
        'You are not authorized to preview this document.',
        403
    );

}


/*
|--------------------------------------------------------------------------
| Project root
|--------------------------------------------------------------------------
*/

$projectRoot = dirname(
    __DIR__,
    2
);

$realProjectRoot = realpath(
    $projectRoot
);

if ($realProjectRoot === false) {

    previewError(
        'Unable to determine application directory.',
        500
    );

}


/*
|--------------------------------------------------------------------------
| Stored file path
|--------------------------------------------------------------------------
*/

$storedFilePath = trim(
    $document['file_path'] ?? ''
);

if ($storedFilePath === '') {

    previewError(
        'The document does not have a file path.',
        404
    );

}


/*
|--------------------------------------------------------------------------
| Normalize path
|--------------------------------------------------------------------------
*/

$storedFilePath = str_replace(
    ['/', '\\'],
    DIRECTORY_SEPARATOR,
    $storedFilePath
);

$storedFilePath = ltrim(
    $storedFilePath,
    "\\/"
);


/*
|--------------------------------------------------------------------------
| Build physical path
|--------------------------------------------------------------------------
*/

$originalFile =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    $storedFilePath;


$realOriginalFile = realpath(
    $originalFile
);

if (
    $realOriginalFile === false ||
    !is_file($realOriginalFile)
) {

    previewError(
        'The document file could not be located on the server.',
        404
    );

}


/*
|--------------------------------------------------------------------------
| Security check
|--------------------------------------------------------------------------
*/

$projectPrefix =
    rtrim(
        $realProjectRoot,
        DIRECTORY_SEPARATOR
    ) .
    DIRECTORY_SEPARATOR;


if (
    stripos(
        $realOriginalFile,
        $projectPrefix
    ) !== 0
) {

    previewError(
        'Invalid document file path.',
        403
    );

}


$originalFile = $realOriginalFile;


/*
|--------------------------------------------------------------------------
| Extension
|--------------------------------------------------------------------------
*/

$extension = strtolower(
    pathinfo(
        $originalFile,
        PATHINFO_EXTENSION
    )
);


$allowedExtensions = [
    'pdf',
    'doc',
    'docx'
];


if (
    !in_array(
        $extension,
        $allowedExtensions,
        true
    )
) {

    previewError(
        'This document type cannot be previewed. Supported types: PDF, DOC and DOCX.',
        415
    );

}


/*
|--------------------------------------------------------------------------
| PDF files
|--------------------------------------------------------------------------
|
| Already a PDF:
| stream the original directly.
|
*/

if ($extension === 'pdf') {

    $fileSize = filesize(
        $originalFile
    );

    if (
        $fileSize === false ||
        $fileSize <= 0
    ) {

        previewError(
            'The PDF file is empty or unreadable.',
            500
        );

    }


    while (ob_get_level() > 0) {

        ob_end_clean();

    }


    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: inline; filename="' .
        basename($originalFile) .
        '"'
    );

    header(
        'Content-Length: ' .
        $fileSize
    );

    header(
        'Cache-Control: private, max-age=0, must-revalidate'
    );

    header(
        'Pragma: public'
    );

    header(
        'Accept-Ranges: bytes'
    );


    readfile(
        $originalFile
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| DOC / DOCX -> persistent cached PDF
|--------------------------------------------------------------------------
*/

/*
 * Important:
 *
 * The cache filename depends on:
 *
 * - document ID
 * - physical file path
 * - file size
 * - file modification time
 *
 * Therefore when the source document changes,
 * a new cache PDF is automatically generated.
 */

$fileSize = filesize(
    $originalFile
);

$fileModified = filemtime(
    $originalFile
);

if (
    $fileSize === false ||
    $fileModified === false
) {

    previewError(
        'Unable to read document file information.',
        500
    );

}


$cacheDirectory =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'documents' .
    DIRECTORY_SEPARATOR .
    'preview_cache';


if (!is_dir($cacheDirectory)) {

    if (
        !mkdir(
            $cacheDirectory,
            0775,
            true
        )
    ) {

        previewError(
            'Unable to create PDF preview cache directory.',
            500
        );

    }

}


/*
|--------------------------------------------------------------------------
| Generate deterministic cache name
|--------------------------------------------------------------------------
*/

$cacheKey = hash(
    'sha256',
    $documentId .
    '|' .
    $originalFile .
    '|' .
    $fileSize .
    '|' .
    $fileModified
);


$cachedPdf =
    $cacheDirectory .
    DIRECTORY_SEPARATOR .
    'document_' .
    $documentId .
    '_' .
    $cacheKey .
    '.pdf';


/*
|--------------------------------------------------------------------------
| Use existing cache
|--------------------------------------------------------------------------
*/

if (
    !file_exists($cachedPdf) ||
    filesize($cachedPdf) <= 0
) {

    /*
    |--------------------------------------------------------------------------
    | Temporary conversion directory
    |--------------------------------------------------------------------------
    */

    $conversionId =
        date('Ymd_His') .
        '_' .
        bin2hex(
            random_bytes(6)
        );


    $conversionDirectory =
        $cacheDirectory .
        DIRECTORY_SEPARATOR .
        'conversion_' .
        $conversionId;


    if (
        !mkdir(
            $conversionDirectory,
            0775,
            true
        )
    ) {

        previewError(
            'Unable to create LibreOffice conversion directory.',
            500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | LibreOffice profile
    |--------------------------------------------------------------------------
    */

    $loProfile =
        $conversionDirectory .
        DIRECTORY_SEPARATOR .
        'lo_profile';


    if (
        !mkdir(
            $loProfile,
            0775,
            true
        )
    ) {

        previewError(
            'Unable to create LibreOffice temporary profile.',
            500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | LibreOffice executable
    |--------------------------------------------------------------------------
    */

    $sofficeCandidates = [

        'C:\\Program Files\\LibreOffice\\program\\soffice.com',

        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'

    ];


    $soffice = null;


    foreach (
        $sofficeCandidates
        as $candidate
    ) {

        if (
            file_exists($candidate)
        ) {

            $soffice = $candidate;

            break;

        }

    }


    if ($soffice === null) {

        $soffice = 'soffice.com';

    }


    /*
    |--------------------------------------------------------------------------
    | Build command
    |--------------------------------------------------------------------------
    */

    $sofficeArg =
        escapeshellarg(
            $soffice
        );


    $inputArg =
        escapeshellarg(
            $originalFile
        );


    $outputArg =
        escapeshellarg(
            $conversionDirectory
        );


    $profileUrl =
        'file:///' .
        str_replace(
            '\\',
            '/',
            $loProfile
        );


    $profileArg =
        escapeshellarg(
            $profileUrl
        );


    $command =
        $sofficeArg .
        ' --headless' .
        ' --convert-to pdf' .
        ' --outdir ' .
        $outputArg .
        ' -env:UserInstallation=' .
        $profileArg .
        ' ' .
        $inputArg .
        ' 2>&1';


    $output = [];

    $returnCode = 0;


    exec(
        $command,
        $output,
        $returnCode
    );


    /*
    |--------------------------------------------------------------------------
    | Expected LibreOffice output
    |--------------------------------------------------------------------------
    */

    $convertedPdf =
        $conversionDirectory .
        DIRECTORY_SEPARATOR .
        pathinfo(
            $originalFile,
            PATHINFO_FILENAME
        ) .
        '.pdf';


    /*
    |--------------------------------------------------------------------------
    | Cleanup LibreOffice profile
    |--------------------------------------------------------------------------
    */

    function removePreviewDirectory($directory)
    {
        if (!is_dir($directory)) {

            return;

        }


        try {

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $directory,
                        FilesystemIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::CHILD_FIRST
                );


            foreach (
                $iterator
                as $file
            ) {

                if ($file->isDir()) {

                    @rmdir(
                        $file->getRealPath()
                    );

                } else {

                    @unlink(
                        $file->getRealPath()
                    );

                }

            }

        } catch (Throwable $e) {

            // Ignore cleanup problems.

        }


        @rmdir(
            $directory
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify conversion
    |--------------------------------------------------------------------------
    */

    if (
        $returnCode !== 0 ||
        !file_exists($convertedPdf)
    ) {

        removePreviewDirectory(
            $conversionDirectory
        );


        $details = '';

        if (!empty($output)) {

            $details =
                ' ' .
                implode(
                    ' ',
                    $output
                );

        }


        previewError(
            'LibreOffice could not convert the document to PDF.' .
            $details,
            500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Verify converted PDF
    |--------------------------------------------------------------------------
    */

    $convertedSize = filesize(
        $convertedPdf
    );


    if (
        $convertedSize === false ||
        $convertedSize <= 0
    ) {

        removePreviewDirectory(
            $conversionDirectory
        );


        previewError(
            'The converted PDF is empty.',
            500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Move converted PDF into permanent cache
    |--------------------------------------------------------------------------
    */

    if (
        file_exists($cachedPdf)
    ) {

        @unlink(
            $cachedPdf
        );

    }


    if (
        !rename(
            $convertedPdf,
            $cachedPdf
        )
    ) {

        /*
         * Fallback for Windows/filesystem situations
         * where rename may fail.
         */

        if (
            !copy(
                $convertedPdf,
                $cachedPdf
            )
        ) {

            removePreviewDirectory(
                $conversionDirectory
            );


            previewError(
                'Unable to save the converted PDF cache.',
                500
            );

        }


        @unlink(
            $convertedPdf
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Cleanup temporary conversion directory
    |--------------------------------------------------------------------------
    */

    removePreviewDirectory(
        $conversionDirectory
    );

}


/*
|--------------------------------------------------------------------------
| Final cache verification
|--------------------------------------------------------------------------
*/

if (
    !file_exists($cachedPdf) ||
    !is_file($cachedPdf)
) {

    previewError(
        'The converted PDF could not be prepared.',
        500
    );

}


$pdfSize = filesize(
    $cachedPdf
);


if (
    $pdfSize === false ||
    $pdfSize <= 0
) {

    previewError(
        'The converted PDF is empty.',
        500
    );

}


/*
|--------------------------------------------------------------------------
| Stream cached PDF
|--------------------------------------------------------------------------
*/

while (ob_get_level() > 0) {

    ob_end_clean();

}


header(
    'Content-Type: application/pdf'
);

header(
    'Content-Disposition: inline; filename="' .
    pathinfo(
        $originalFile,
        PATHINFO_FILENAME
    ) .
    '.pdf"'
);

header(
    'Content-Length: ' .
    $pdfSize
);

header(
    'Cache-Control: private, max-age=0, must-revalidate'
);

header(
    'Pragma: public'
);

header(
    'Accept-Ranges: bytes'
);


readfile(
    $cachedPdf
);

exit;