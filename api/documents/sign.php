<?php

/**
 * Document Digital Signature Endpoint
 *
 * Coordinate system:
 *
 * x      = PDF points from LEFT
 * y      = PDF points from BOTTOM
 * width  = PDF points
 * height = PDF points
 *
 * The browser uses PDF.js to convert the displayed
 * browser coordinates into real PDF coordinates.
 *
 * FPDI/FPDF is configured to use POINTS as its
 * document unit so the coordinates can be used
 * directly.
 */

error_reporting(E_ALL);
ini_set("display_errors", "0");

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once __DIR__ . "/../../includes/ApprovalRouter.php";
require_once __DIR__ . "/../../vendor/autoload.php";

use setasign\Fpdi\Fpdi;

Auth::protect();

header(
    "Content-Type: application/json; charset=UTF-8"
);


/* ================================================================
   RESPONSE
   ================================================================ */

function signResponse(
    bool $success,
    string $message,
    array $extra = [],
    int $statusCode = 200
) {

    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        )
    );

    exit;
}


/* ================================================================
   REQUEST METHOD
   ================================================================ */

if (
    ($_SERVER["REQUEST_METHOD"] ?? "") !== "POST"
) {

    signResponse(
        false,
        "Invalid request method.",
        [],
        405
    );
}


/* ================================================================
   USER
   ================================================================ */

$user =
    Auth::getCurrentUser();

if (
    !$user ||
    empty($user["id"])
) {

    signResponse(
        false,
        "Unable to identify the current user.",
        [],
        401
    );
}


/* ================================================================
   DOCUMENT ID
   ================================================================ */

$documentId =
    (int)(
        $_POST["id"] ?? 0
    );

if (
    $documentId <= 0
) {

    signResponse(
        false,
        "Invalid document ID.",
        [],
        400
    );
}


/* ================================================================
   SIGNATURE
   ================================================================ */

$signatureData =
    trim(
        $_POST["signature"] ?? ""
    );


if (
    $signatureData === ""
) {

    signResponse(
        false,
        "No signature was provided.",
        [],
        400
    );
}


/*
 * IMPORTANT:
 *
 * Correct regex:
 *
 * data:image/png;base64,...
 */
if (
    !preg_match(
        '/^data:image\/png;base64,/i',
        $signatureData
    )
) {

    signResponse(
        false,
        "Invalid signature format. PNG signature required.",
        [],
        400
    );
}


/* ================================================================
   DECODE SIGNATURE
   ================================================================ */

$signatureBase64 =
    preg_replace(
        '/^data:image\/png;base64,/i',
        "",
        $signatureData
    );


$signatureBinary =
    base64_decode(
        $signatureBase64,
        true
    );


if (
    $signatureBinary === false
) {

    signResponse(
        false,
        "The signature image could not be decoded.",
        [],
        400
    );
}


/* ================================================================
   VALIDATE PNG
   ================================================================ */

$imageInfo =
    @getimagesizefromstring(
        $signatureBinary
    );


if (
    $imageInfo === false ||
    ($imageInfo["mime"] ?? "") !== "image/png"
) {

    signResponse(
        false,
        "The signature image is not a valid PNG.",
        [],
        400
    );
}


/* ================================================================
   SIGNATURE SIZE
   ================================================================ */

if (
    strlen($signatureBinary) >
    5 * 1024 * 1024
) {

    signResponse(
        false,
        "The signature image is too large.",
        [],
        400
    );
}


/* ================================================================
   PLACEMENTS
   ================================================================ */

$placementsJson =
    trim(
        $_POST["placements"] ?? ""
    );


if (
    $placementsJson === ""
) {

    signResponse(
        false,
        "No signature placement was provided.",
        [],
        400
    );
}


$placements =
    json_decode(
        $placementsJson,
        true
    );


if (
    !is_array($placements) ||
    empty($placements)
) {

    signResponse(
        false,
        "Invalid signature placement data.",
        [],
        400
    );
}


/* ================================================================
   VALIDATE PLACEMENTS
   ================================================================ */

$validatedPlacements = [];


foreach (
    $placements as $placement
) {

    if (
        !is_array($placement)
    ) {

        signResponse(
            false,
            "Invalid signature placement.",
            [],
            400
        );
    }


    /*
     * New coordinate system marker.
     */
    $coordinateSystem =
        strtolower(
            trim(
                $placement["coordinateSystem"]
                    ?? ""
            )
        );


    if (
        $coordinateSystem !== "pdf"
    ) {

        signResponse(
            false,
            "Invalid coordinate system. PDF coordinates are required.",
            [],
            400
        );
    }


    $page =
        (int)(
            $placement["page"] ?? 0
        );


    $x =
        (float)(
            $placement["x"] ?? -1
        );


    $y =
        (float)(
            $placement["y"] ?? -1
        );


    $width =
        (float)(
            $placement["width"] ?? 0
        );


    $height =
        (float)(
            $placement["height"] ?? 0
        );


    /* ------------------------------------------------------------
       BASIC VALIDATION
    ------------------------------------------------------------ */

    if (
        $page < 1
    ) {

        signResponse(
            false,
            "Invalid signature page.",
            [],
            400
        );
    }


    if (
        !is_finite($x) ||
        !is_finite($y) ||
        !is_finite($width) ||
        !is_finite($height)
    ) {

        signResponse(
            false,
            "Invalid signature coordinates.",
            [],
            400
        );
    }


    if (
        $x < 0 ||
        $y < 0
    ) {

        signResponse(
            false,
            "Signature coordinates cannot be negative.",
            [],
            400
        );
    }


    if (
        $width <= 0 ||
        $height <= 0
    ) {

        signResponse(
            false,
            "Signature dimensions must be greater than zero.",
            [],
            400
        );
    }


    /*
     * Prevent obviously malicious/accidental
     * oversized values.
     */
    if (
        $width > 5000 ||
        $height > 5000 ||
        $x > 10000 ||
        $y > 10000
    ) {

        signResponse(
            false,
            "Signature coordinates are outside the allowed range.",
            [],
            400
        );
    }


    $validatedPlacements[] = [

        "page" =>
            $page,

        "x" =>
            $x,

        "y" =>
            $y,

        "width" =>
            $width,

        "height" =>
            $height
    ];
}


/* ================================================================
   GET DOCUMENT
   ================================================================ */

$document =
    fetchRow(
        "
        SELECT *
        FROM documents
        WHERE id = ?
        ",
        [
            $documentId
        ]
    );


if (!$document) {

    signResponse(
        false,
        "Document not found.",
        [],
        404
    );
}


/* ================================================================
   APPROVAL PERMISSION
   ================================================================ */

if (
    !ApprovalRouter::canUserApproveDocument(
        (int)$user["id"],
        $documentId
    )
) {

    signResponse(
        false,
        "You are not authorized to sign this document.",
        [],
        403
    );
}


/* ================================================================
   PROJECT ROOT
   ================================================================ */

$projectRoot =
    dirname(
        __DIR__,
        2
    );


$realProjectRoot =
    realpath(
        $projectRoot
    );


if (
    $realProjectRoot === false
) {

    signResponse(
        false,
        "Unable to determine application directory.",
        [],
        500
    );
}


/* ================================================================
   ORIGINAL FILE
   ================================================================ */

$storedFilePath =
    trim(
        $document["file_path"] ?? ""
    );


if (
    $storedFilePath === ""
) {

    signResponse(
        false,
        "The document does not have an uploaded file.",
        [],
        404
    );
}


$storedFilePath =
    str_replace(
        [
            "/",
            "\\"
        ],
        DIRECTORY_SEPARATOR,
        $storedFilePath
    );


$storedFilePath =
    ltrim(
        $storedFilePath,
        "\\/"
    );


$originalFile =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    $storedFilePath;


$realOriginalFile =
    realpath(
        $originalFile
    );


if (
    $realOriginalFile === false
) {

    signResponse(
        false,
        "The original document file could not be found.",
        [],
        404
    );
}


/* ================================================================
   PATH SECURITY
   ================================================================ */

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

    signResponse(
        false,
        "Invalid document file path.",
        [],
        403
    );
}


$originalFile =
    $realOriginalFile;


if (
    !is_file($originalFile) ||
    !is_readable($originalFile)
) {

    signResponse(
        false,
        "The original document cannot be read.",
        [],
        404
    );
}


/* ================================================================
   EXTENSION
   ================================================================ */

$extension =
    strtolower(
        pathinfo(
            $originalFile,
            PATHINFO_EXTENSION
        )
    );


if (
    !in_array(
        $extension,
        [
            "pdf",
            "doc",
            "docx"
        ],
        true
    )
) {

    signResponse(
        false,
        "Unsupported document type.",
        [],
        415
    );
}


/* ================================================================
   DOCUMENT DIRECTORIES
   ================================================================ */

$documentsDirectory =
    $projectRoot .
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

        signResponse(
            false,
            "Unable to create documents directory.",
            [],
            500
        );
    }
}


$signedTempDirectory =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    "signed_temp";


if (
    !is_dir(
        $signedTempDirectory
    )
) {

    if (
        !mkdir(
            $signedTempDirectory,
            0775,
            true
        )
    ) {

        signResponse(
            false,
            "Unable to create signing temporary directory.",
            [],
            500
        );
    }
}


/* ================================================================
   UNIQUE ID
   ================================================================ */

$uniqueId =
    date("Ymd_His") .
    "_" .
    bin2hex(
        random_bytes(6)
    );


/* ================================================================
   TEMP SIGNATURE
   ================================================================ */

$tempSignature =
    $signedTempDirectory .
    DIRECTORY_SEPARATOR .
    "signature_" .
    $uniqueId .
    ".png";


if (
    file_put_contents(
        $tempSignature,
        $signatureBinary
    ) === false
) {

    signResponse(
        false,
        "Unable to save the signature temporarily.",
        [],
        500
    );
}


/* ================================================================
   CLEANUP HELPER
   ================================================================ */

function removeSignConversionDirectory(
    string $directory
): void {

    if (
        !is_dir($directory)
    ) {
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
            $iterator as $file
        ) {

            if (
                $file->isDir()
            ) {

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

        // Ignore cleanup failures.
    }


    @rmdir(
        $directory
    );
}


/* ================================================================
   WORKING PDF
   ================================================================ */

$workingPdf = "";


/* ================================================================
   PDF
   ================================================================ */

if (
    $extension === "pdf"
) {

    $workingPdf =
        $signedTempDirectory .
        DIRECTORY_SEPARATOR .
        "source_" .
        $uniqueId .
        ".pdf";


    if (
        !copy(
            $originalFile,
            $workingPdf
        )
    ) {

        @unlink(
            $tempSignature
        );

        signResponse(
            false,
            "Unable to prepare the PDF for signing.",
            [],
            500
        );
    }
}


/* ================================================================
   DOC / DOCX
   ================================================================ */

if (
    $extension === "doc" ||
    $extension === "docx"
) {

    $fileSize =
        filesize(
            $originalFile
        );


    $fileModified =
        filemtime(
            $originalFile
        );


    if (
        $fileSize === false ||
        $fileModified === false
    ) {

        @unlink(
            $tempSignature
        );

        signResponse(
            false,
            "Unable to read document file information.",
            [],
            500
        );
    }


    /* ------------------------------------------------------------
       PREVIEW CACHE
    ------------------------------------------------------------ */

    $cacheDirectory =
        $documentsDirectory .
        DIRECTORY_SEPARATOR .
        "preview_cache";


    if (
        !is_dir($cacheDirectory)
    ) {

        if (
            !mkdir(
                $cacheDirectory,
                0775,
                true
            )
        ) {

            @unlink(
                $tempSignature
            );

            signResponse(
                false,
                "Unable to create PDF preview cache directory.",
                [],
                500
            );
        }
    }


    /* ------------------------------------------------------------
       CACHE KEY
    ------------------------------------------------------------ */

    $cacheKey =
        hash(
            "sha256",
            $documentId .
            "|" .
            $originalFile .
            "|" .
            $fileSize .
            "|" .
            $fileModified
        );


    $cachedPdf =
        $cacheDirectory .
        DIRECTORY_SEPARATOR .
        "document_" .
        $documentId .
        "_" .
        $cacheKey .
        ".pdf";


    /* ------------------------------------------------------------
       CREATE CACHE IF NECESSARY
    ------------------------------------------------------------ */

    if (
        !file_exists($cachedPdf) ||
        filesize($cachedPdf) <= 0
    ) {

        $conversionId =
            date("Ymd_His") .
            "_" .
            bin2hex(
                random_bytes(6)
            );


        $conversionDirectory =
            $cacheDirectory .
            DIRECTORY_SEPARATOR .
            "conversion_" .
            $conversionId;


        $loProfile =
            $conversionDirectory .
            DIRECTORY_SEPARATOR .
            "lo_profile";


        if (
            !mkdir(
                $loProfile,
                0775,
                true
            )
        ) {

            @unlink(
                $tempSignature
            );

            signResponse(
                false,
                "Unable to create LibreOffice temporary profile.",
                [],
                500
            );
        }


        /* --------------------------------------------------------
           LIBREOFFICE
        -------------------------------------------------------- */

        $sofficeCandidates = [

            "C:\\Program Files\\LibreOffice\\program\\soffice.com",

            "C:\\Program Files\\LibreOffice\\program\\soffice.exe",

            "C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com",

            "C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe"
        ];


        $soffice = null;


        foreach (
            $sofficeCandidates as $candidate
        ) {

            if (
                file_exists($candidate)
            ) {

                $soffice =
                    $candidate;

                break;
            }
        }


        if (
            $soffice === null
        ) {

            $soffice =
                "soffice.com";
        }


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
            "file:///" .
            str_replace(
                "\\",
                "/",
                $loProfile
            );


        $profileArg =
            escapeshellarg(
                $profileUrl
            );


        $command =
            $sofficeArg .
            " --headless" .
            " --convert-to pdf" .
            " --outdir " .
            $outputArg .
            " -env:UserInstallation=" .
            $profileArg .
            " " .
            $inputArg .
            " 2>&1";


        $output = [];

        $returnCode = 0;


        exec(
            $command,
            $output,
            $returnCode
        );


        $convertedPdf =
            $conversionDirectory .
            DIRECTORY_SEPARATOR .
            pathinfo(
                $originalFile,
                PATHINFO_FILENAME
            ) .
            ".pdf";


        if (
            $returnCode !== 0 ||
            !file_exists($convertedPdf)
        ) {

            @unlink(
                $tempSignature
            );


            $details = "";


            if (
                !empty($output)
            ) {

                $details =
                    " " .
                    implode(
                        " ",
                        $output
                    );
            }


            removeSignConversionDirectory(
                $conversionDirectory
            );


            signResponse(
                false,
                "LibreOffice could not convert the document to PDF." .
                $details,
                [],
                500
            );
        }


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

            if (
                !copy(
                    $convertedPdf,
                    $cachedPdf
                )
            ) {

                @unlink(
                    $tempSignature
                );


                removeSignConversionDirectory(
                    $conversionDirectory
                );


                signResponse(
                    false,
                    "Unable to save the converted PDF.",
                    [],
                    500
                );
            }


            @unlink(
                $convertedPdf
            );
        }


        removeSignConversionDirectory(
            $conversionDirectory
        );
    }


    /* ------------------------------------------------------------
       VERIFY CACHE
    ------------------------------------------------------------ */

    if (
        !file_exists($cachedPdf) ||
        filesize($cachedPdf) <= 0
    ) {

        @unlink(
            $tempSignature
        );

        signResponse(
            false,
            "The converted PDF could not be prepared.",
            [],
            500
        );
    }


    /* ------------------------------------------------------------
       COPY CACHE TO WORKSPACE
    ------------------------------------------------------------ */

    $workingPdf =
        $signedTempDirectory .
        DIRECTORY_SEPARATOR .
        "source_" .
        $uniqueId .
        ".pdf";


    if (
        !copy(
            $cachedPdf,
            $workingPdf
        )
    ) {

        @unlink(
            $tempSignature
        );

        signResponse(
            false,
            "Unable to prepare the converted PDF for signing.",
            [],
            500
        );
    }
}


/* ================================================================
   VERIFY WORKING PDF
   ================================================================ */

if (
    !file_exists($workingPdf) ||
    !is_readable($workingPdf)
) {

    @unlink(
        $tempSignature
    );

    signResponse(
        false,
        "The PDF prepared for signing could not be found.",
        [],
        500
    );
}


/* ================================================================
   FINAL FILE
   ================================================================ */

$baseName =
    pathinfo(
        $originalFile,
        PATHINFO_FILENAME
    );


$finalFileName =
    $baseName .
    "_signed_" .
    date("Ymd_His") .
    "_" .
    bin2hex(
        random_bytes(3)
    ) .
    ".pdf";


$finalFile =
    $documentsDirectory .
    DIRECTORY_SEPARATOR .
    $finalFileName;


$reviewedFile =
    "uploads/documents/" .
    $finalFileName;


/* ================================================================
   GENERATE PDF
   ================================================================ */

try {

    /*
     * IMPORTANT:
     *
     * "pt" means PDF points.
     *
     * 72 points = 1 inch.
     *
     * This makes the coordinates received
     * from PDF.js directly usable by FPDF.
     */
    $pdf =
        new Fpdi(
            "P",
            "pt"
        );


    /* ------------------------------------------------------------
       IMPORT SOURCE
    ------------------------------------------------------------ */

    $pageCount =
        $pdf->setSourceFile(
            $workingPdf
        );


    if (
        $pageCount < 1
    ) {

        throw new RuntimeException(
            "The source PDF contains no pages."
        );
    }


    /* ------------------------------------------------------------
       PROCESS PAGES
    ------------------------------------------------------------ */

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


        if (
            !is_array($size)
        ) {

            throw new RuntimeException(
                "Unable to determine PDF page dimensions."
            );
        }


        /*
         * Because FPDF is using "pt",
         * these are PDF points.
         */
        $pageWidth =
            (float)$size["width"];


        $pageHeight =
            (float)$size["height"];


        $orientation =
            $pageWidth >
            $pageHeight
                ? "L"
                : "P";


        /* --------------------------------------------------------
           ADD EXACT PAGE SIZE
        -------------------------------------------------------- */

        $pdf->AddPage(
            $orientation,
            [
                $pageWidth,
                $pageHeight
            ]
        );


        /* --------------------------------------------------------
           ORIGINAL PAGE
        -------------------------------------------------------- */

        $pdf->useTemplate(
            $templateId,
            0,
            0,
            $pageWidth,
            $pageHeight
        );


        /* --------------------------------------------------------
           SIGNATURES ON THIS PAGE
        -------------------------------------------------------- */

        foreach (
            $validatedPlacements
            as $placement
        ) {

            if (
                (int)$placement["page"] !==
                $pageNo
            ) {
                continue;
            }


            /*
             * Coordinates from the browser:
             *
             * x = left
             * y = bottom
             *
             * FPDI/FPDF:
             *
             * x = left
             * y = top
             *
             * Therefore:
             *
             * topY =
             *     pageHeight -
             *     y -
             *     height
             */
            $signatureX =
                (float)$placement["x"];


            $signatureBottomY =
                (float)$placement["y"];


            $signatureWidth =
                (float)$placement["width"];


            $signatureHeight =
                (float)$placement["height"];


            $signatureY =
                $pageHeight -
                $signatureBottomY -
                $signatureHeight;


            /* ----------------------------------------------------
               SAFETY BOUNDARIES
            ---------------------------------------------------- */

            $signatureX =
                max(
                    0,
                    min(
                        $signatureX,
                        $pageWidth -
                        $signatureWidth
                    )
                );


            $signatureY =
                max(
                    0,
                    min(
                        $signatureY,
                        $pageHeight -
                        $signatureHeight
                    )
                );


            /* ----------------------------------------------------
               SIGNATURE
            ---------------------------------------------------- */

            $pdf->Image(
                $tempSignature,
                $signatureX,
                $signatureY,
                $signatureWidth,
                $signatureHeight,
                "PNG"
            );
        }
    }


    /* ============================================================
       WRITE FILE
       ============================================================ */

    $pdf->Output(
        "F",
        $finalFile
    );

} catch (
    Throwable $e
) {

    @unlink(
        $tempSignature
    );

    @unlink(
        $workingPdf
    );

    @unlink(
        $finalFile
    );


    signResponse(
        false,
        "Unable to generate the signed PDF: " .
        $e->getMessage(),
        [],
        500
    );
}


/* ================================================================
   VERIFY FINAL PDF
   ================================================================ */

if (
    !file_exists($finalFile) ||
    filesize($finalFile) <= 0
) {

    @unlink(
        $tempSignature
    );

    @unlink(
        $workingPdf
    );


    signResponse(
        false,
        "The signed PDF could not be generated.",
        [],
        500
    );
}


/* ================================================================
   UPDATE DATABASE
   ================================================================ */

try {

    $updated =
        updateData(
            "documents",
            [
                "reviewed_file" =>
                    $reviewedFile,

                "reviewed_by" =>
                    (int)$user["id"],

                "reviewed_at" =>
                    date(
                        "Y-m-d H:i:s"
                    )
            ],
            [
                "id" =>
                    $documentId
            ]
        );


    if (!$updated) {

        @unlink(
            $finalFile
        );

        @unlink(
            $tempSignature
        );

        @unlink(
            $workingPdf
        );


        signResponse(
            false,
            "The signed PDF was generated, but the document record could not be updated.",
            [],
            500
        );
    }

} catch (
    Throwable $e
) {

    @unlink(
        $finalFile
    );

    @unlink(
        $tempSignature
    );

    @unlink(
        $workingPdf
    );


    signResponse(
        false,
        "The signed PDF was generated, but saving the document record failed.",
        [],
        500
    );
}


/* ================================================================
   CLEANUP
   ================================================================ */

@unlink(
    $tempSignature
);

@unlink(
    $workingPdf
);


/* ================================================================
   SUCCESS
   ================================================================ */

signResponse(
    true,
    "Signed PDF generated successfully.",
    [
        "reviewed_file" =>
            $reviewedFile,

        "reviewed_url" =>
            $reviewedFile
    ]
);