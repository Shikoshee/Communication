<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

header("Content-Type: application/json");

try {

    require_once "../../includes/config.php";
    require_once "../../includes/auth.php";
    require_once "../../includes/Permission.php";

    Auth::protect();

    if (!Permission::canApprove()) {

        echo json_encode([
            "success" => false,
            "message" => "Permission denied"
        ]);

        exit;
    }

    $id = (int)($_POST["id"] ?? 0);

    if ($id <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid document ID"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Get document
    |--------------------------------------------------------------------------
    */

    $document = fetchRow(
        "SELECT * FROM documents WHERE id=?",
        [$id]
    );

    if (!$document) {

        echo json_encode([
            "success" => false,
            "message" => "Document not found"
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Project paths
    |--------------------------------------------------------------------------
    */

    $projectRoot = realpath(dirname(__DIR__, 2));

    if (!$projectRoot) {

        throw new Exception(
            "Could not determine project root."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Original file
    |--------------------------------------------------------------------------
    */

    $relativeFile = trim($document["file_path"]);

    if ($relativeFile === "") {

        throw new Exception(
            "Document has no file path."
        );
    }

    /*
    | Convert database path into filesystem path.
    |
    | Example:
    | uploads/documents/file.xlsx
    |
    | becomes:
    | C:/xampp/htdocs/Communication/uploads/documents/file.xlsx
    */

    $relativeFile = ltrim(
        str_replace("\\", "/", $relativeFile),
        "/"
    );

    $sourceFile = $projectRoot . DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $relativeFile
        );

    $sourceFile = realpath($sourceFile);

    if (!$sourceFile || !file_exists($sourceFile)) {

        throw new Exception(
            "Original document file was not found: " .
            $relativeFile
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check file extension
    |--------------------------------------------------------------------------
    */

    $extension = strtolower(
        pathinfo($sourceFile, PATHINFO_EXTENSION)
    );

    $allowed = [
        "pdf",
        "docx",
        "xlsx"
    ];

    if (!in_array($extension, $allowed, true)) {

        throw new Exception(
            "Unsupported document type: ." . $extension
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create signing directory
    |--------------------------------------------------------------------------
    */

    $signingDirectory =
        $projectRoot .
        DIRECTORY_SEPARATOR .
        "uploads" .
        DIRECTORY_SEPARATOR .
        "documents" .
        DIRECTORY_SEPARATOR .
        "signed";

    if (!is_dir($signingDirectory)) {

        if (!mkdir($signingDirectory, 0777, true)) {

            throw new Exception(
                "Could not create signing directory."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Unique working filename
    |--------------------------------------------------------------------------
    */

    $baseName =
        "document_" .
        $id . "_" .
        time();

    /*
    |--------------------------------------------------------------------------
    | PDF destination
    |--------------------------------------------------------------------------
    */

    $pdfFile =
        $signingDirectory .
        DIRECTORY_SEPARATOR .
        $baseName .
        ".pdf";

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    if ($extension === "pdf") {

        if (!copy($sourceFile, $pdfFile)) {

            throw new Exception(
                "Could not copy PDF into signing directory."
            );
        }

    }

    /*
    |--------------------------------------------------------------------------
    | DOCX / XLSX
    |--------------------------------------------------------------------------
    */

    else {

        /*
        | LibreOffice executable
        */

        $soffice = "C:\\Program Files\\LibreOffice\\program\\soffice.exe";

        if (!file_exists($soffice)) {

            throw new Exception(
                "LibreOffice was not found at: " . $soffice
            );
        }

        /*
        | Temporary conversion directory
        */

        $conversionDirectory =
            $signingDirectory .
            DIRECTORY_SEPARATOR .
            "conversion_" .
            $id .
            "_" .
            time();

        if (!mkdir($conversionDirectory, 0777, true)) {

            throw new Exception(
                "Could not create LibreOffice conversion directory."
            );
        }

        /*
        | LibreOffice command
        */

        $command =
            '"' . $soffice . '"' .
            ' --headless' .
            ' --convert-to pdf' .
            ' --outdir ' .
            escapeshellarg($conversionDirectory) .
            ' ' .
            escapeshellarg($sourceFile) .
            ' 2>&1';

        /*
        | Execute LibreOffice
        */

        $output = [];

        $returnCode = 0;

        exec(
            $command,
            $output,
            $returnCode
        );

        /*
        | Check LibreOffice result
        */

        if ($returnCode !== 0) {

            throw new Exception(
                "LibreOffice conversion failed: " .
                implode("\n", $output)
            );
        }

        /*
        | LibreOffice normally creates:
        |
        | originalfilename.pdf
        */

        $convertedFile =
            $conversionDirectory .
            DIRECTORY_SEPARATOR .
            pathinfo(
                $sourceFile,
                PATHINFO_FILENAME
            ) .
            ".pdf";

        if (!file_exists($convertedFile)) {

            /*
            | Search conversion directory in case
            | LibreOffice used a slightly different name.
            */

            $files = glob(
                $conversionDirectory .
                DIRECTORY_SEPARATOR .
                "*.pdf"
            );

            if (!$files) {

                throw new Exception(
                    "LibreOffice completed but no PDF was created.\n" .
                    implode("\n", $output)
                );
            }

            $convertedFile = $files[0];
        }

        /*
        | Move converted PDF into signing directory
        */

        if (!copy($convertedFile, $pdfFile)) {

            throw new Exception(
                "Could not copy converted PDF into signing directory."
            );
        }

        /*
        | Cleanup temporary conversion directory
        */

        foreach (
            glob(
                $conversionDirectory .
                DIRECTORY_SEPARATOR .
                "*"
            ) as $temporaryFile
        ) {

            if (is_file($temporaryFile)) {

                @unlink($temporaryFile);
            }
        }

        @rmdir($conversionDirectory);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify PDF
    |--------------------------------------------------------------------------
    */

    if (!file_exists($pdfFile)) {

        throw new Exception(
            "Signing PDF was not created."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Composer
    |--------------------------------------------------------------------------
    */

    $autoload =
        $projectRoot .
        DIRECTORY_SEPARATOR .
        "vendor" .
        DIRECTORY_SEPARATOR .
        "autoload.php";

    if (!file_exists($autoload)) {

        throw new Exception(
            "Composer autoload file not found."
        );
    }

    require_once $autoload;

    /*
    |--------------------------------------------------------------------------
    | Count PDF pages using FPDI
    |--------------------------------------------------------------------------
    */

    $pdf = new \setasign\Fpdi\Fpdi();

    $pageCount = $pdf->setSourceFile($pdfFile);

    if ($pageCount <= 0) {

        throw new Exception(
            "The generated PDF contains no pages."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public URL
    |--------------------------------------------------------------------------
    */

    $relativePdf =
        "uploads/documents/signed/" .
        basename($pdfFile);

    $fileUrl =
        rtrim(APP_URL, "/") .
        "/" .
        $relativePdf;

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "message" =>
            "Document prepared successfully.",

        "document_id" =>
            $id,

        "file_url" =>
            $fileUrl,

        "file_path" =>
            $relativePdf,

        "page_count" =>
            $pageCount

    ]);

}
catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" =>
            "Document preparation failed.",

        "error" =>
            $e->getMessage(),

        "file" =>
            $e->getFile(),

        "line" =>
            $e->getLine()

    ]);
}