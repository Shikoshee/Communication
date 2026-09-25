
<?php

/**
 * Digital Document Signing Endpoint
 *
 * Workflow:
 * 1. Receive signature PNG from browser
 * 2. Verify logged-in user can approve the document
 * 3. Convert DOC/DOCX to PDF using LibreOffice
 * 4. Embed signature into the final PDF
 * 5. Save signed PDF as reviewed_file
 * 6. Save reviewer and timestamp
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/ApprovalRouter.php";

Auth::protect();

$user = Auth::getCurrentUser();


/**
 * Send JSON response and stop execution.
 */
function jsonResponse($success, $message, $extra = [])
{
    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        )
    );

    exit;
}


/**
 * Only POST requests are accepted.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    jsonResponse(
        false,
        'Invalid request method.'
    );

}


/**
 * Validate document ID.
 */
if (!isset($_POST['id'])) {

    jsonResponse(
        false,
        'Document ID missing.'
    );

}


$documentId = (int)$_POST['id'];

if ($documentId <= 0) {

    jsonResponse(
        false,
        'Invalid document ID.'
    );

}


/**
 * Validate signature.
 */
if (
    !isset($_POST['signature']) ||
    empty($_POST['signature'])
) {

    jsonResponse(
        false,
        'Signature missing.'
    );

}


$signatureData = $_POST['signature'];


/**
 * Only accept PNG data URLs.
 */
if (
    strpos(
        $signatureData,
        'data:image/png;base64,'
    ) !== 0
) {

    jsonResponse(
        false,
        'Invalid signature format.'
    );

}


/**
 * Remove data URL prefix.
 */
$signatureBase64 = substr(
    $signatureData,
    strlen('data:image/png;base64,')
);


/**
 * Decode signature.
 */
$signatureBinary = base64_decode(
    $signatureBase64,
    true
);


if ($signatureBinary === false) {

    jsonResponse(
        false,
        'Unable to decode signature.'
    );

}


/**
 * Prevent unnecessarily large signature uploads.
 *
 * Maximum: 2 MB
 */
if (strlen($signatureBinary) > 2 * 1024 * 1024) {

    jsonResponse(
        false,
        'Signature image is too large.'
    );

}


/**
 * Locate document.
 */
$document = fetchRow(
    "SELECT *
     FROM documents
     WHERE id = ?",
    [$documentId]
);


if (!$document) {

    jsonResponse(
        false,
        'Document not found.'
    );

}


/**
 * Verify that the current user is allowed
 * to approve/sign this document.
 */
if (
    !ApprovalRouter::canUserApproveDocument(
        $user['id'],
        $documentId
    )
) {

    jsonResponse(
        false,
        'You are not authorized to sign this document.'
    );

}


/**
 * Determine project root.
 *
 * This file is:
 *
 * Communication/api/documents/sign.php
 *
 * dirname(__DIR__, 2) therefore gives:
 *
 * Communication/
 */
$projectRoot = dirname(
    __DIR__,
    2
);


/**
 * Resolve original document path.
 *
 * The database normally stores something such as:
 *
 * uploads/documents/example.pdf
 *
 * We convert that into the physical Windows path.
 */
$storedFilePath = $document['file_path'] ?? '';

if (empty($storedFilePath)) {

    jsonResponse(
        false,
        'The document does not have a file path.'
    );

}


/**
 * Normalize path separators.
 */
$storedFilePath = str_replace(
    ['/', '\\'],
    DIRECTORY_SEPARATOR,
    $storedFilePath
);


/**
 * Remove leading separators.
 */
$storedFilePath = ltrim(
    $storedFilePath,
    "\\/"
);


/**
 * Build absolute path.
 */
$originalFile = $projectRoot .
    DIRECTORY_SEPARATOR .
    $storedFilePath;


/**
 * Security check:
 * prevent paths from escaping project directory.
 */
$realProjectRoot = realpath(
    $projectRoot
);

$realOriginalFile = realpath(
    $originalFile
);


if (
    $realProjectRoot === false ||
    $realOriginalFile === false
) {

    jsonResponse(
        false,
        'The original document file could not be located.'
    );

}


$projectPrefix = rtrim(
    $realProjectRoot,
    DIRECTORY_SEPARATOR
) . DIRECTORY_SEPARATOR;


if (
    stripos(
        $realOriginalFile,
        $projectPrefix
    ) !== 0
) {

    jsonResponse(
        false,
        'Invalid document file path.'
    );

}


$originalFile = $realOriginalFile;


/**
 * Verify original file exists.
 */
if (!file_exists($originalFile)) {

    jsonResponse(
        false,
        'The original document file does not exist on the server.'
    );

}


/**
 * Determine extension.
 */
$extension = strtolower(
    pathinfo(
        $originalFile,
        PATHINFO_EXTENSION
    )
);


/**
 * Supported document types.
 */
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

    jsonResponse(
        false,
        'This document type cannot currently be digitally signed. Supported types: PDF, DOC and DOCX.'
    );

}


/**
 * Working directories.
 */
$documentsDirectory =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'documents';


$tempDirectory =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    'signed_temp';


/**
 * Create directories if necessary.
 */
if (!is_dir($documentsDirectory)) {

    if (
        !mkdir(
            $documentsDirectory,
            0775,
            true
        )
    ) {

        jsonResponse(
            false,
            'Unable to create the documents directory.'
        );

    }

}


if (!is_dir($tempDirectory)) {

    if (
        !mkdir(
            $tempDirectory,
            0775,
            true
        )
    ) {

        jsonResponse(
            false,
            'Unable to create the temporary signing directory.'
        );

    }

}


/**
 * Generate unique working names.
 */
$uniqueId =
    date('Ymd_His') .
    '_' .
    bin2hex(
        random_bytes(5)
    );


$tempSignature =
    $tempDirectory .
    DIRECTORY_SEPARATOR .
    'signature_' .
    $uniqueId .
    '.png';


$workingPdf =
    $tempDirectory .
    DIRECTORY_SEPARATOR .
    'document_' .
    $uniqueId .
    '.pdf';


$convertedPdf =
    $tempDirectory .
    DIRECTORY_SEPARATOR .
    pathinfo(
        $originalFile,
        PATHINFO_FILENAME
    ) .
    '.pdf';


/**
 * Save signature image.
 */
if (
    file_put_contents(
        $tempSignature,
        $signatureBinary
    ) === false
) {

    jsonResponse(
        false,
        'Unable to save the signature image.'
    );

}


/**
 * Make sure the saved file is actually a PNG.
 */
$imageInfo = @getimagesize(
    $tempSignature
);


if (
    $imageInfo === false ||
    ($imageInfo['mime'] ?? '') !== 'image/png'
) {

    @unlink($tempSignature);

    jsonResponse(
        false,
        'The signature image is not a valid PNG file.'
    );

}


/**
 * PDF source preparation.
 */
if ($extension === 'pdf') {

    /**
     * Original is already PDF.
     *
     * Copy it to the working location so
     * the original remains untouched.
     */
    if (
        !copy(
            $originalFile,
            $workingPdf
        )
    ) {

        @unlink($tempSignature);

        jsonResponse(
            false,
            'Unable to prepare the PDF for signing.'
        );

    }

} else {

    /**
     * DOC/DOCX conversion.
     *
     * LibreOffice is used in headless mode.
     */
    $sofficeCandidates = [

        'C:\\Program Files\\LibreOffice\\program\\soffice.com',

        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',

        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'

    ];


    $soffice = null;


    foreach ($sofficeCandidates as $candidate) {

        if (
            file_exists($candidate)
        ) {

            $soffice = $candidate;

            break;

        }

    }


    /**
     * Final fallback.
     */
    if ($soffice === null) {

        $soffice = 'soffice.com';

    }


    /**
     * Unique LibreOffice profile.
     *
     * This prevents conflicts when multiple
     * conversions happen.
     */
    $loProfile =
        $tempDirectory .
        DIRECTORY_SEPARATOR .
        'lo_profile_' .
        $uniqueId;


    if (!mkdir(
        $loProfile,
        0775,
        true
    )) {

        @unlink($tempSignature);

        jsonResponse(
            false,
            'Unable to create LibreOffice temporary profile.'
        );

    }


    /**
     * Escape Windows shell arguments.
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
            $tempDirectory
        );

    $profileArg =
        escapeshellarg(
            'file:///' .
            str_replace(
                '\\',
                '/',
                $loProfile
            )
        );


    /**
     * Convert to PDF.
     */
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


    /**
     * LibreOffice creates the PDF using
     * the original filename.
     */
    $expectedConvertedPdf =
        $tempDirectory .
        DIRECTORY_SEPARATOR .
        pathinfo(
            $originalFile,
            PATHINFO_FILENAME
        ) .
        '.pdf';


    /**
     * Check conversion result.
     */
    if (
        $returnCode !== 0 ||
        !file_exists($expectedConvertedPdf)
    ) {

        @unlink($tempSignature);

        /**
         * Remove LibreOffice profile.
         */
        if (is_dir($loProfile)) {

            $profileFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $loProfile,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($profileFiles as $file) {

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

            @rmdir(
                $loProfile
            );

        }


        $details = '';

        if (!empty($output)) {

            $details =
                ' LibreOffice: ' .
                implode(
                    ' ',
                    $output
                );

        }


        jsonResponse(
            false,
            'LibreOffice could not convert the document to PDF.' .
            $details
        );

    }


    /**
     * Move converted PDF to our predictable
     * working filename.
     */
    if (
        !rename(
            $expectedConvertedPdf,
            $workingPdf
        )
    ) {

        @unlink($tempSignature);

        jsonResponse(
            false,
            'Unable to prepare the converted PDF.'
        );

    }


    /**
     * Clean LibreOffice profile.
     */
    if (is_dir($loProfile)) {

        $profileFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $loProfile,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($profileFiles as $file) {

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

        @rmdir(
            $loProfile
        );

    }

}


/**
 * Verify the working PDF.
 */
if (
    !file_exists($workingPdf) ||
    filesize($workingPdf) <= 0
) {

    @unlink($tempSignature);
    @unlink($workingPdf);

    jsonResponse(
        false,
        'The PDF could not be prepared for signing.'
    );

}


/**
 * Load Composer.
 */
$autoload = $projectRoot .
    DIRECTORY_SEPARATOR .
    'vendor' .
    DIRECTORY_SEPARATOR .
    'autoload.php';


if (!file_exists($autoload)) {

    @unlink($tempSignature);
    @unlink($workingPdf);

    jsonResponse(
        false,
        'Composer autoload file was not found.'
    );

}


require_once $autoload;


/**
 * Load FPDI.
 */
use setasign\Fpdi\Fpdi;


/**
 * Generate output filename.
 */
$title = $document['title'] ?? 'document';


/**
 * Clean title for use in filename.
 */
$safeTitle = preg_replace(
    '/[^A-Za-z0-9_-]+/',
    '_',
    $title
);


$safeTitle = trim(
    $safeTitle,
    '_'
);


if ($safeTitle === '') {

    $safeTitle = 'document';

}


$outputFileName =
    $safeTitle .
    '_signed_' .
    date('Ymd_His') .
    '_' .
    bin2hex(
        random_bytes(3)
    ) .
    '.pdf';


$outputFile =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    $outputFileName;


/**
 * Relative path stored in database.
 */
$reviewedFile =
    'uploads/documents/' .
    $outputFileName;


/**
 * Create signed PDF.
 */
try {

    $pdf = new Fpdi();


    /**
     * Determine number of pages.
     */
    $pageCount =
        $pdf->setSourceFile(
            $workingPdf
        );


    if ($pageCount < 1) {

        throw new Exception(
            'The PDF contains no pages.'
        );

    }


    /**
     * Import every page.
     */
    for (
        $pageNo = 1;
        $pageNo <= $pageCount;
        $pageNo++
    ) {

        $templateId =
            $pdf->importPage(
                $pageNo
            );


        $size =
            $pdf->getTemplateSize(
                $templateId
            );


        /**
         * Determine orientation.
         */
        $orientation =
            ($size['width'] > $size['height'])
            ? 'L'
            : 'P';


        $pdf->AddPage(
            $orientation,
            [
                $size['width'],
                $size['height']
            ]
        );


        $pdf->useTemplate(
            $templateId
        );


        /**
         * Add signature only to the final page.
         */
        if ($pageNo === $pageCount) {

            /**
             * Signature dimensions in mm.
             */
            $signatureWidth = 45;
            $signatureHeight = 18;


            /**
             * Position:
             * bottom-right of final page.
             */
            $rightMargin = 20;
            $bottomMargin = 25;


            $x =
                $size['width'] -
                $signatureWidth -
                $rightMargin;


            $y =
                $size['height'] -
                $signatureHeight -
                $bottomMargin;


            /**
             * Signature image.
             */
            $pdf->Image(
                $tempSignature,
                $x,
                $y,
                $signatureWidth,
                $signatureHeight,
                'PNG'
            );


            /**
             * Small label beneath signature.
             */
            $pdf->SetFont(
                'Arial',
                '',
                7
            );


            $pdf->SetTextColor(
                80,
                80,
                80
            );


            $pdf->SetXY(
                $x,
                $y + $signatureHeight + 1
            );


            $pdf->Cell(
                $signatureWidth,
                4,
                'Digitally signed',
                0,
                0,
                'C'
            );

        }

    }


    /**
     * Write final signed PDF.
     */
    $pdf->Output(
        'F',
        $outputFile
    );


} catch (Throwable $e) {

    @unlink($tempSignature);
    @unlink($workingPdf);

    jsonResponse(
        false,
        'Unable to create the signed PDF: ' .
        $e->getMessage()
    );

}


/**
 * Verify output was actually created.
 */
if (
    !file_exists($outputFile) ||
    filesize($outputFile) <= 0
) {

    @unlink($tempSignature);
    @unlink($workingPdf);

    jsonResponse(
        false,
        'The signed PDF was not created.'
    );

}


/**
 * Update document record.
 *
 * IMPORTANT:
 * Your project's updateData() function accepts:
 *
 * updateData($table, $data, $where)
 *
 * where must be an associative array.
 */
$updateResult = updateData(
    'documents',
    [
        'reviewed_file' => $reviewedFile,
        'reviewed_by'   => (int)$user['id'],
        'reviewed_at'   => date('Y-m-d H:i:s')
    ],
    [
        'id' => $documentId
    ]
);


/**
 * Make sure database update succeeded.
 */
if (
    !isset($updateResult['success']) ||
    !$updateResult['success']
) {

    /**
     * The signed file was created, but the database
     * could not be updated. Remove the orphaned file.
     */
    @unlink($outputFile);

    @unlink($tempSignature);
    @unlink($workingPdf);

    jsonResponse(
        false,
        'The signed PDF was created, but the document record could not be updated.',
        [
            'database_error' =>
                $updateResult['error'] ?? 'Unknown database error'
        ]
    );

}


/**
 * Cleanup temporary files.
 */
@unlink($tempSignature);
@unlink($workingPdf);


/**
 * Browser URL.
 */
$reviewedUrl =
    '/Communication/' .
    str_replace(
        '\\',
        '/',
        $reviewedFile
    );


/**
 * Success.
 */
jsonResponse(
    true,
    'Signed document generated successfully.',
    [
        'reviewed_file' => $reviewedFile,
        'reviewed_url'  => $reviewedUrl
    ]
);

