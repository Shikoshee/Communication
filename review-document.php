<?php

require_once "includes/config.php";
require_once "includes/auth.php";
require_once __DIR__ . '/includes/ApprovalRouter.php';

Auth::protect();

$user = Auth::getCurrentUser();

$id = (int)($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get document
|--------------------------------------------------------------------------
*/

$document = fetchRow("

SELECT

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

", [$id]);


if (!$document) {
    die("Document not found");
}


/*
|--------------------------------------------------------------------------
| Check approval permission
|--------------------------------------------------------------------------
*/

if (!ApprovalRouter::canUserApproveDocument($user['id'], $id)) {
    die("You do not have permission to approve this document");
}


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/review.css">

<style>

/*
|--------------------------------------------------------------------------
| Digital Signature Section
|--------------------------------------------------------------------------
*/

.signature-section {
    margin-top: 25px;
    padding: 25px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.signature-section h3 {
    margin: 0 0 8px;
}

.signature-section-description {
    color: #6b7280;
    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| Signature tabs
|--------------------------------------------------------------------------
*/

.signature-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.signature-tab {
    border: 1px solid #d1d5db;
    background: #f9fafb;
    padding: 10px 18px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
}

.signature-tab.active {
    background: #111827;
    color: #fff;
    border-color: #111827;
}


/*
|--------------------------------------------------------------------------
| Signature panels
|--------------------------------------------------------------------------
*/

.signature-panel {
    display: none;
}

.signature-panel.active {
    display: block;
}


/*
|--------------------------------------------------------------------------
| Canvas
|--------------------------------------------------------------------------
*/

.signature-canvas-wrapper {
    width: 100%;
    max-width: 700px;
}

#signatureCanvas {
    display: block;
    width: 100%;
    height: 220px;
    min-height: 220px;
    background: #ffffff;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    cursor: crosshair;
    touch-action: none;
    box-sizing: border-box;
}
.signature-hint {
    margin-top: 8px;
    font-size: 13px;
    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| Typed signature
|--------------------------------------------------------------------------
*/

#typedSignature {
    width: 100%;
    max-width: 700px;
    padding: 15px;
    font-size: 32px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    outline: none;
}

#typedSignature:focus {
    border-color: #6b7280;
}

.typed-signature-preview {
    margin-top: 15px;
    min-height: 80px;
    max-width: 700px;
    padding: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    display: flex;
    align-items: center;
    background: #fafafa;
    font-family: "Brush Script MT", "Segoe Script", cursive;
    font-size: 42px;
}


/*
|--------------------------------------------------------------------------
| Signature controls
|--------------------------------------------------------------------------
*/

.signature-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.signature-btn {
    border: none;
    border-radius: 7px;
    padding: 11px 18px;
    cursor: pointer;
    font-weight: 600;
}

.clear-signature-btn {
    background: #f3f4f6;
    color: #374151;
}

.apply-signature-btn {
    background: #2563eb;
    color: #fff;
}

.generate-signed-btn {
    background: #16a34a;
    color: #fff;
}

.generate-signed-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}


/*
|--------------------------------------------------------------------------
| Signature result
|--------------------------------------------------------------------------
*/

.signature-result {
    display: none;
    margin-top: 20px;
    padding: 15px;
    border-radius: 8px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.signature-result.show {
    display: block;
}


/*
|--------------------------------------------------------------------------
| Signature preview
|--------------------------------------------------------------------------
*/

.applied-signature-preview {
    display: none;
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    background: #fff;
}

.applied-signature-preview.show {
    display: block;
}

.applied-signature-preview h4 {
    margin-top: 0;
}

#appliedSignatureImage {
    max-width: 400px;
    max-height: 150px;
}


/*
|--------------------------------------------------------------------------
| Existing signed document
|--------------------------------------------------------------------------
*/

.signed-preview {
    margin: 20px 0;
    padding: 18px;
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.signed-download {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

</style>

<div class="page-header">

<h1>
    Review Document
</h1>

<p>
    Review, sign and approve submitted documents.
</p>

</div>

<div class="review-card">


<!-- ================================================================
     DOCUMENT HEADER
     ================================================================ -->

<div class="review-header">

    <div class="document-icon">

        <i class="fa fa-file-signature"></i>

    </div>

    <div class="document-details">

        <h2>
            <?= htmlspecialchars($document['title']) ?>
        </h2>

        <div class="document-meta">

            <span>
                <i class="fa fa-user"></i>

                <?= htmlspecialchars($document['uploader']) ?>
            </span>


            <span>

                <i class="fa fa-calendar"></i>

                <?= date(
                    "d M Y H:i",
                    strtotime($document["created_at"])
                ) ?>

            </span>


            <span>

                <i class="fa fa-layer-group"></i>

                Version <?= htmlspecialchars($document["version"]) ?>

            </span>


            <span class="status <?= htmlspecialchars($document["status"]) ?>">

                <?= ucfirst(htmlspecialchars($document["status"])) ?>

            </span>

        </div>

    </div>

</div>



<!-- ================================================================
     DOCUMENT PREVIEW
     ================================================================ -->

<div class="preview-card">

    <div class="preview-header">

        <h3>

            <i class="fa fa-eye"></i>

            Document Preview

        </h3>

    </div>


    <?php

    $filePath = trim($document['file_path']);


    /*
    |--------------------------------------------------------------------------
    | Get file extension
    |--------------------------------------------------------------------------
    */

    $fileExtension = strtolower(
        pathinfo(
            parse_url($filePath, PHP_URL_PATH),
            PATHINFO_EXTENSION
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Build document URL
    |--------------------------------------------------------------------------
    */

    if (
        preg_match('#^https?://#i', $filePath)
    ) {

        if (
            str_starts_with(
                strtolower($filePath),
                'https://localhost'
            )
        ) {

            $relativePath = parse_url(
                $filePath,
                PHP_URL_PATH
            );

            $documentUrl =
                rtrim(APP_URL, '/') .
                '/' .
                ltrim($relativePath, '/');

        } else {

            $documentUrl = $filePath;

        }

    } else {

        $documentUrl =
            rtrim(APP_URL, '/') .
            '/' .
            ltrim($filePath, '/');

    }


    $documentUrl = htmlspecialchars(
        $documentUrl,
        ENT_QUOTES,
        'UTF-8'
    );


    /*
    |--------------------------------------------------------------------------
    | Office application
    |--------------------------------------------------------------------------
    */

    $wordExtensions = [
        'pdf',
        'doc',
        'docx'
    ];

    $excelExtensions = [
        'xls',
        'xlsx',
        'csv'
    ];


    $isWord = in_array(
        $fileExtension,
        $wordExtensions,
        true
    );

    $isExcel = in_array(
        $fileExtension,
        $excelExtensions,
        true
    );

    ?>


    <div class="document-preview">

        <?php if ($isWord): ?>

            <a
                href="ms-word:ofe|u|<?= $documentUrl ?>"
                class="open-office-btn word-btn"
            >

                <i class="fa fa-file-word"></i>

                Open with Microsoft Word

            </a>


        <?php elseif ($isExcel): ?>

            <a
                href="ms-excel:ofe|u|<?= $documentUrl ?>"
                class="open-office-btn excel-btn"
            >

                <i class="fa fa-file-excel"></i>

                Open with Microsoft Excel

            </a>

        <?php endif; ?>

    </div>


</div>



<hr>



<!-- ================================================================
     EXISTING SIGNED COPY
     ================================================================ -->

<?php if (!empty($document["reviewed_file"])): ?>

    <div class="signed-preview">

        <div>

            <h4>

                <i class="fa fa-check-circle"></i>

                Signed Copy Already Generated

            </h4>

            <p>

                A reviewed version has already been generated.

            </p>

        </div>


        <a
            href="<?= htmlspecialchars($document["reviewed_file"]) ?>"
            target="_blank"
            class="signed-download"
        >

            <i class="fa fa-download"></i>

            View Signed Copy

        </a>

    </div>

<?php endif; ?>



<!-- ================================================================
     DIGITAL SIGNATURE
     ================================================================ -->

<div class="signature-section">

    <h3>

        <i class="fa fa-signature"></i>

        Digital Signature

    </h3>


    <p class="signature-section-description">

        Sign the document electronically before generating the signed copy.

        You can draw your signature or type one.

    </p>



    <!-- Signature tabs -->

    <div class="signature-tabs">

        <button
            type="button"
            class="signature-tab active"
            data-panel="drawSignaturePanel"
        >

            <i class="fa fa-pen"></i>

            Draw Signature

        </button>


        <button
            type="button"
            class="signature-tab"
            data-panel="typeSignaturePanel"
        >

            <i class="fa fa-keyboard"></i>

            Type Signature

        </button>

    </div>



    <!-- ============================================================
         DRAW SIGNATURE
         ============================================================ -->

    <div
        id="drawSignaturePanel"
        class="signature-panel active"
    >

        <div class="signature-canvas-wrapper">

            <canvas id="signatureCanvas"></canvas>

            <div class="signature-hint">

                Draw your signature inside the box using your mouse,
                touch screen or trackpad.

            </div>

        </div>

    </div>



    <!-- ============================================================
         TYPE SIGNATURE
         ============================================================ -->

    <div
        id="typeSignaturePanel"
        class="signature-panel"
    >

        <input
            type="text"
            id="typedSignature"
            placeholder="Type your name"
            autocomplete="off"
        >


        <div
            id="typedSignaturePreview"
            class="typed-signature-preview"
        >

            Your signature

        </div>

    </div>



    <!-- ============================================================
         SIGNATURE CONTROLS
         ============================================================ -->

    <div class="signature-controls">

        <button
            type="button"
            id="clearSignature"
            class="signature-btn clear-signature-btn"
        >

            <i class="fa fa-eraser"></i>

            Clear

        </button>


        <button
            type="button"
            id="applySignature"
            class="signature-btn apply-signature-btn"
        >

            <i class="fa fa-check"></i>

            Apply Signature

        </button>


        <button
            type="button"
            id="generateSignedCopy"
            class="signature-btn generate-signed-btn"
            disabled
        >

            <i class="fa fa-file-signature"></i>

            Generate Signed Copy

        </button>

    </div>



    <!-- ============================================================
         APPLIED SIGNATURE PREVIEW
         ============================================================ -->

    <div
        id="appliedSignaturePreview"
        class="applied-signature-preview"
    >

        <h4>

            <i class="fa fa-check-circle"></i>

            Signature Applied

        </h4>


        <p>

            Your signature is ready to be added to the document.

        </p>


        <img
            id="appliedSignatureImage"
            src=""
            alt="Applied signature"
        >

    </div>



    <!-- ============================================================
         STAGE 1 NOTICE
         ============================================================ -->

    <div
        id="signatureResult"
        class="signature-result"
    >

        <strong>Signature ready.</strong>

        The signature has been prepared successfully.

        The actual PDF generation and database update will be connected
        in the next stage.

    </div>

</div>



<!-- ================================================================
     APPROVAL DECISION
     ================================================================ -->

<div class="action-card">

    <h3>
        Approval Decision
    </h3>

    <p>
        After reviewing and signing, choose the final action.
    </p>


    <div class="review-actions">

        <button
            onclick="approveDocument(<?= $document['id'] ?>)"
            class="approve-btn"
            <?php if (!ApprovalRouter::canUserApproveDocument($user['id'], $id)): ?>
                disabled
                title="You don't have permission to approve this document"
            <?php endif; ?>
        >

            <i class="fa fa-check"></i>

            Approve Document

        </button>


        <button
            onclick="rejectDocument(<?= $document['id'] ?>)"
            class="reject-btn"
            <?php if (!ApprovalRouter::canUserApproveDocument($user['id'], $id)): ?>
                disabled
                title="You don't have permission to approve this document"
            <?php endif; ?>
        >

            <i class="fa fa-times"></i>

            Reject

        </button>

    </div>

</div>


</div>

<script src="assets/js/review.js"></script>

<script src="assets/js/approvals.js"></script>

<script>


/*
|--------------------------------------------------------------------------
| DIGITAL SIGNATURE
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", function () {

    const canvas = document.getElementById("signatureCanvas");
    const typedSignature = document.getElementById("typedSignature");
    const typedSignaturePreview = document.getElementById("typedSignaturePreview");

    const clearButton = document.getElementById("clearSignature");
    const applyButton = document.getElementById("applySignature");
    const generateButton = document.getElementById("generateSignedCopy");

    const appliedPreview = document.getElementById("appliedSignaturePreview");
    const appliedImage = document.getElementById("appliedSignatureImage");
    const signatureResult = document.getElementById("signatureResult");

    /*
    |--------------------------------------------------------------------------
    | CHECK ELEMENTS
    |--------------------------------------------------------------------------
    */

    if (!canvas) {
        console.error("Digital Signature: canvas not found.");
        return;
    }

    if (!typedSignature || !typedSignaturePreview ||
        !clearButton || !applyButton || !generateButton ||
        !appliedPreview || !appliedImage || !signatureResult) {

        console.error("Digital Signature: one or more elements are missing.");
        return;
    }

    const ctx = canvas.getContext("2d");

    if (!ctx) {
        console.error("Digital Signature: unable to get canvas context.");
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | VARIABLES
    |--------------------------------------------------------------------------
    */

    let drawing = false;
    let hasDrawing = false;
    let appliedSignature = null;


    /*
    |--------------------------------------------------------------------------
    | CANVAS SETUP
    |--------------------------------------------------------------------------
    */

    function setupCanvas() {

        const rect = canvas.getBoundingClientRect();

        const width = Math.max(
            Math.floor(rect.width),
            300
        );

        const height = 220;

        const ratio = Math.max(
            window.devicePixelRatio || 1,
            1
        );

        canvas.width = width * ratio;
        canvas.height = height * ratio;

        canvas.style.width = width + "px";
        canvas.style.height = height + "px";

        ctx.setTransform(1, 0, 0, 1, 0, 0);

        ctx.scale(ratio, ratio);

        ctx.lineWidth = 2.5;
        ctx.lineCap = "round";
        ctx.lineJoin = "round";
        ctx.strokeStyle = "#111827";

        ctx.fillStyle = "#ffffff";

        ctx.fillRect(
            0,
            0,
            width,
            height
        );

        hasDrawing = false;
    }


    setupCanvas();


    /*
    |--------------------------------------------------------------------------
    | GET POINTER POSITION
    |--------------------------------------------------------------------------
    */

    function getPointerPosition(event) {

        const rect = canvas.getBoundingClientRect();

        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top
        };
    }


    /*
    |--------------------------------------------------------------------------
    | START DRAWING
    |--------------------------------------------------------------------------
    */

    canvas.addEventListener("pointerdown", function (event) {

        event.preventDefault();

        drawing = true;
        hasDrawing = true;

        try {
            canvas.setPointerCapture(event.pointerId);
        } catch (error) {
            console.warn("Pointer capture unavailable.");
        }

        const position = getPointerPosition(event);

        ctx.beginPath();

        ctx.moveTo(
            position.x,
            position.y
        );
    });


    /*
    |--------------------------------------------------------------------------
    | DRAW
    |--------------------------------------------------------------------------
    */

    canvas.addEventListener("pointermove", function (event) {

        if (!drawing) {
            return;
        }

        event.preventDefault();

        const position = getPointerPosition(event);

        ctx.lineTo(
            position.x,
            position.y
        );

        ctx.stroke();
    });


    /*
    |--------------------------------------------------------------------------
    | STOP DRAWING
    |--------------------------------------------------------------------------
    */

    function stopDrawing(event) {

        if (!drawing) {
            return;
        }

        drawing = false;

        ctx.closePath();

        if (
            event &&
            event.pointerId !== undefined
        ) {

            try {
                canvas.releasePointerCapture(
                    event.pointerId
                );
            } catch (error) {
                // Ignore.
            }
        }
    }


    canvas.addEventListener(
        "pointerup",
        stopDrawing
    );

    canvas.addEventListener(
        "pointercancel",
        stopDrawing
    );


    /*
    |--------------------------------------------------------------------------
    | TYPED SIGNATURE
    |--------------------------------------------------------------------------
    */

    typedSignature.addEventListener("input", function () {

        const value = typedSignature.value.trim();

        if (!value) {

            typedSignaturePreview.textContent =
                "Your signature";

            return;
        }

        typedSignaturePreview.textContent = value;
    });


    /*
    |--------------------------------------------------------------------------
    | SIGNATURE TABS
    |--------------------------------------------------------------------------
    */

    const signatureTabs =
        document.querySelectorAll(".signature-tab");

    const signaturePanels =
        document.querySelectorAll(".signature-panel");


    signatureTabs.forEach(function (tab) {

        tab.addEventListener("click", function (event) {

            event.preventDefault();

            const targetPanel =
                tab.dataset.panel;

            signatureTabs.forEach(function (item) {

                item.classList.remove("active");

            });

            signaturePanels.forEach(function (panel) {

                panel.classList.remove("active");

            });

            tab.classList.add("active");

            const panel =
                document.getElementById(targetPanel);

            if (panel) {
                panel.classList.add("active");
            }

        });

    });


    /*
    |--------------------------------------------------------------------------
    | CLEAR SIGNATURE
    |--------------------------------------------------------------------------
    */

    clearButton.addEventListener("click", function (event) {

        event.preventDefault();

        setupCanvas();

        typedSignature.value = "";

        typedSignaturePreview.textContent =
            "Your signature";

        appliedSignature = null;

        appliedImage.src = "";

        appliedPreview.classList.remove("show");

        signatureResult.classList.remove("show");

        generateButton.disabled = true;

        generateButton.innerHTML =
            '<i class="fa fa-file-signature"></i> Generate Signed Copy';

    });


    /*
    |--------------------------------------------------------------------------
    | CREATE TYPED SIGNATURE IMAGE
    |--------------------------------------------------------------------------
    */

    function createTypedSignature() {

        const value =
            typedSignature.value.trim();

        if (!value) {
            return null;
        }

        const tempCanvas =
            document.createElement("canvas");

        tempCanvas.width = 1000;
        tempCanvas.height = 300;

        const tempCtx =
            tempCanvas.getContext("2d");

        tempCtx.fillStyle = "#ffffff";

        tempCtx.fillRect(
            0,
            0,
            1000,
            300
        );

        tempCtx.fillStyle = "#111827";

        tempCtx.font =
            '72px "Brush Script MT", "Segoe Script", cursive';

        tempCtx.textBaseline = "middle";

        tempCtx.fillText(
            value,
            40,
            150
        );

        return tempCanvas.toDataURL("image/png");
    }


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT SIGNATURE
    |--------------------------------------------------------------------------
    */

    function getSignatureImage() {

        const activeTab =
            document.querySelector(".signature-tab.active");

        if (!activeTab) {
            return null;
        }


        /*
        | DRAW
        */

        if (
            activeTab.dataset.panel ===
            "drawSignaturePanel"
        ) {

            if (!hasDrawing) {
                return null;
            }

            return canvas.toDataURL("image/png");
        }


        /*
        | TYPE
        */

        if (
            activeTab.dataset.panel ===
            "typeSignaturePanel"
        ) {

            return createTypedSignature();
        }

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | APPLY SIGNATURE
    |--------------------------------------------------------------------------
    */

    applyButton.addEventListener("click", function (event) {

        event.preventDefault();

        const signature =
            getSignatureImage();

        if (!signature) {

            alert(
                "Please draw or type your signature first."
            );

            return;
        }

        appliedSignature = signature;

        appliedImage.src =
            appliedSignature;

        appliedPreview.classList.add("show");

        signatureResult.innerHTML = `
            <strong>
                <i class="fa fa-check-circle"></i>
                Signature Applied
            </strong>

            <br><br>

            Your signature is ready to be added to the document.
        `;

        signatureResult.classList.add("show");

        generateButton.disabled = false;

    });


    /*
    |--------------------------------------------------------------------------
    | GENERATE SIGNED COPY
    |--------------------------------------------------------------------------
    */

    generateButton.addEventListener("click", async function (event) {

        event.preventDefault();

        if (!appliedSignature) {

            alert(
                "Please apply your signature first."
            );

            return;
        }

        generateButton.disabled = true;

        const originalText =
            generateButton.innerHTML;

        generateButton.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Generating...';


        try {

            const formData =
                new FormData();

            formData.append(
                "id",
                "<?= (int)$document['id'] ?>"
            );

            formData.append(
                "signature",
                appliedSignature
            );


            const response =
                await fetch(
                    "api/documents/sign.php",
                    {
                        method: "POST",
                        body: formData
                    }
                );


            const responseText =
                await response.text();

            console.log(
                "Signing server response:",
                responseText
            );


            let result;

            try {

                result =
                    JSON.parse(responseText);

            } catch (jsonError) {

                console.error(
                    "Invalid JSON response:",
                    responseText
                );

                throw new Error(
                    "The server returned an invalid response."
                );
            }


            if (!result.success) {

                throw new Error(
                    result.message ||
                    "Unable to generate signed document."
                );
            }


            signatureResult.innerHTML = `

                <strong>
                    <i class="fa fa-check-circle"></i>
                    Signed document generated successfully.
                </strong>

                <br><br>

                Your signed PDF has been saved.

                <br><br>

                <a
                    href="${result.reviewed_url}"
                    target="_blank"
                    class="signed-download"
                >

                    <i class="fa fa-file-pdf"></i>

                    View Signed PDF

                </a>

            `;

            signatureResult.classList.add("show");

            generateButton.innerHTML =
                '<i class="fa fa-check"></i> Signed Copy Generated';


            setTimeout(function () {

                window.location.reload();

            }, 2000);


        } catch (error) {

            console.error(
                "Signing error:",
                error
            );

            alert(
                error.message ||
                "Something went wrong while generating the signed document."
            );

            generateButton.disabled = false;

            generateButton.innerHTML =
                originalText;
        }

    });


    /*
    |--------------------------------------------------------------------------
    | INITIALIZED
    |--------------------------------------------------------------------------
    */

    console.log(
        "Digital signature system initialized successfully."
    );

});

</script>




