<?php

require_once "includes/config.php";
require_once "includes/auth.php";
require_once __DIR__ . "/includes/ApprovalRouter.php";

Auth::protect();

$user = Auth::getCurrentUser();

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("Invalid document ID");
}


/*
|--------------------------------------------------------------------------
| Get document
|--------------------------------------------------------------------------
*/

$document = fetchRow(
    "
    SELECT
        d.*,
        CONCAT(u.first_name, ' ', u.last_name) AS uploader
    FROM documents d
    LEFT JOIN users u
        ON u.id = d.uploaded_by
    WHERE d.id = ?
    ",
    [$id]
);

if (!$document) {
    die("Document not found");
}


/*
|--------------------------------------------------------------------------
| Check approval permission
|--------------------------------------------------------------------------
*/

if (!ApprovalRouter::canUserApproveDocument($user["id"], $id)) {
    die("You do not have permission to approve this document");
}


/*
|--------------------------------------------------------------------------
| File information
|--------------------------------------------------------------------------
*/

$filePath = trim(
    $document["file_path"] ?? ""
);

$fileExtension = strtolower(
    pathinfo(
        parse_url(
            $filePath,
            PHP_URL_PATH
        ),
        PATHINFO_EXTENSION
    )
);


/*
|--------------------------------------------------------------------------
| ONLYOFFICE editor URL
|--------------------------------------------------------------------------
*/

$editorBaseUrl = rtrim(
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/communication',
    '/'
);

$editorUrl = $editorBaseUrl . '/document-editor.php?id=' . (int)$document['id'];

/*
|--------------------------------------------------------------------------
| Signed copy URL
|--------------------------------------------------------------------------
*/

$signedCopyUrl = "";

if (!empty($document["reviewed_file"])) {

    $signedCopyUrl =
        rtrim(APP_URL, "/") .
        "/" .
        ltrim(
            $document["reviewed_file"],
            "/"
        );
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>


<link
    rel="stylesheet"
    href="assets/css/review.css"
>


<style>


/* ================================================================
   ONLYOFFICE EDITOR LAUNCH
   ================================================================ */

.onlyoffice-editor-launch {
    margin: 18px 0;
    padding: 18px;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    background: #eff6ff;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    flex-wrap: wrap;
}

.onlyoffice-editor-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.onlyoffice-editor-icon {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background: #2563eb;
    color: #fff;

    font-size: 20px;

    flex-shrink: 0;
}

.onlyoffice-editor-info strong {
    display: block;

    color: #1e3a8a;

    font-size: 16px;

    margin-bottom: 4px;
}

.onlyoffice-editor-info p {
    margin: 0;

    color: #475569;

    font-size: 13px;

    line-height: 1.5;
}

.open-editor-btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 9px;

    padding: 11px 18px;

    border-radius: 7px;

    background: #2563eb;
    color: #fff;

    text-decoration: none;

    font-size: 14px;
    font-weight: 600;

    white-space: nowrap;

    transition: background .2s ease,
                transform .1s ease;
}

.open-editor-btn:hover {
    background: #1d4ed8;
    color: #fff;
    text-decoration: none;
}

.open-editor-btn:active {
    transform: translateY(1px);
}

@media (max-width: 768px) {

    .onlyoffice-editor-launch {
        align-items: stretch;
        flex-direction: column;
    }

    .open-editor-btn {
        width: 100%;
        box-sizing: border-box;
    }

}
    /*
|--------------------------------------------------------------------------
| AJAX Confirmation Modal
|--------------------------------------------------------------------------
*/


.ajax-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 99999;
}

.ajax-modal {
    width: 100%;
    max-width: 460px;
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    animation: ajaxModalIn 0.2s ease;
}

.ajax-modal-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fee2e2;
    color: #dc2626;
    font-size: 24px;
}

.ajax-modal h3 {
    margin: 0 0 12px;
    font-size: 21px;
    color: #1f2937;
}

.ajax-modal p {
    margin: 8px 0;
    color: #6b7280;
    line-height: 1.6;
}

.ajax-modal-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 25px;
}

.ajax-modal-actions button {
    border: none;
    border-radius: 7px;
    padding: 11px 18px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
}

.ajax-modal-cancel {
    background: #e5e7eb;
    color: #374151;
}

.ajax-modal-cancel:hover {
    background: #d1d5db;
}

.ajax-modal-confirm {
    background: #dc2626;
    color: #fff;
}

.ajax-modal-confirm:hover {
    background: #b91c1c;
}

.ajax-modal-confirm:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}


/*
|--------------------------------------------------------------------------
| AJAX Notification
|--------------------------------------------------------------------------
*/

.ajax-notification {
    position: fixed;
    top: 25px;
    right: 25px;
    min-width: 320px;
    max-width: 450px;
    padding: 15px 18px;
    border-radius: 9px;
    background: #fff;
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.18);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 100000;
    opacity: 0;
    transform: translateY(-15px);
    transition: all 0.3s ease;
}

.ajax-notification.show {
    opacity: 1;
    transform: translateY(0);
}

.ajax-notification.success {
    border-left: 4px solid #16a34a;
}

.ajax-notification.success > i {
    color: #16a34a;
}

.ajax-notification.error {
    border-left: 4px solid #dc2626;
}

.ajax-notification.error > i {
    color: #dc2626;
}

.ajax-notification span {
    flex: 1;
    color: #374151;
    font-size: 14px;
    line-height: 1.5;
}

.ajax-notification-close {
    border: none;
    background: transparent;
    color: #9ca3af;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
}

.ajax-notification-close:hover {
    color: #374151;
}


/*
|--------------------------------------------------------------------------
| Modal Animation
|--------------------------------------------------------------------------
*/

@keyframes ajaxModalIn {

    from {
        opacity: 0;
        transform: scale(0.95);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }

}

/* ================================================================
   DIGITAL SIGNATURE PLACEMENT
   ================================================================ */

   .signed-preview-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.remove-signed-btn {
    background: #dc2626;
    color: #fff;
}

.remove-signed-btn:hover {
    background: #b91c1c;
}

.placement-controls {
    margin-top: 25px;
}

.placement-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.placement-toolbar strong {
    display: block;
    margin-bottom: 5px;
}

.placement-toolbar small {
    display: block;
    color: #64748b;
}

.placement-toolbar-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* =========================================================
   PDF SIGNATURE PLACEMENT VIEWER
   ========================================================= */

.pdf-placement-wrapper {
    width: 100%;
}

.pdf-placement-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.pdf-zoom-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.pdf-zoom-btn {
    min-width: 38px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    color: #334155;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: .15s ease;
}

.pdf-zoom-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.pdf-zoom-btn:active {
    transform: translateY(1px);
}

.pdf-zoom-value {
    min-width: 64px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}

.pdf-viewer-help {
    font-size: 13px;
    color: #64748b;
}

.pdf-placement-viewer {
    width: 100%;
    height: 850px;
    overflow: auto;
    padding: 24px;
    background: #e5e7eb;
    border-radius: 12px;
    box-sizing: border-box;
}

/*
|--------------------------------------------------------------------------
| PDF pages
|--------------------------------------------------------------------------
*/

.pdf-page {
    position: relative;
    width: 100%;
    max-width: none;
    margin: 0 auto 30px;
    background: #fff;
    box-shadow: 0 4px 18px rgba(0, 0, 0, .18);
    overflow: visible;
    transform-origin: top center;
}

/*
|--------------------------------------------------------------------------
| Canvas
|--------------------------------------------------------------------------
*/

.pdf-page canvas {
    display: block;
    width: 100%;
    height: auto;
}

/*
|--------------------------------------------------------------------------
| Signature overlay
|--------------------------------------------------------------------------
*/

.pdf-overlay {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

/*
|--------------------------------------------------------------------------
| Page number
|--------------------------------------------------------------------------
*/

.placement-page-number {
    position: absolute;
    left: 8px;
    bottom: 8px;
    z-index: 20;
    padding: 4px 8px;
    border-radius: 5px;
    background: rgba(15, 23, 42, .78);
    color: #fff;
    font-size: 11px;
    pointer-events: none;
}

/*
|--------------------------------------------------------------------------
| Signature placement
|--------------------------------------------------------------------------
*/

.signature-placement {
    position: absolute;
    box-sizing: border-box;

    border: 2px dashed #2563eb;
    background: rgba(255, 255, 255, .12);

    cursor: move;

    pointer-events: auto;

    min-width: 55px;
    min-height: 30px;

    user-select: none;
    touch-action: none;

    z-index: 10;
}

/*
|--------------------------------------------------------------------------
| Selected signature
|--------------------------------------------------------------------------
*/

.signature-placement.selected {
    border: 2px solid #16a34a;

    box-shadow:
        0 0 0 2px rgba(22, 163, 74, .18),
        0 4px 12px rgba(0, 0, 0, .12);
}

/*
|--------------------------------------------------------------------------
| Signature image
|--------------------------------------------------------------------------
*/

.signature-placement img {
    position: absolute;

    left: 0;
    top: 0;

    width: 100%;
    height: 100%;

    object-fit: contain;

    pointer-events: none;
}

/*
|--------------------------------------------------------------------------
| Resize handle
|--------------------------------------------------------------------------
*/

.signature-resize-handle {
    position: absolute;

    right: -7px;
    bottom: -7px;

    width: 14px;
    height: 14px;

    border-radius: 3px;

    background: #2563eb;
    border: 2px solid #fff;

    cursor: nwse-resize;

    z-index: 30;
}

/*
|--------------------------------------------------------------------------
| Placement toolbar
|--------------------------------------------------------------------------
*/

.signature-placement-toolbar {
    position: absolute;

    top: -42px;
    left: 0;

    display: flex;
    align-items: center;

    gap: 5px;

    padding: 5px;

    background: #0f172a;

    border-radius: 7px;

    white-space: nowrap;

    z-index: 50;

    box-shadow: 0 5px 15px rgba(0, 0, 0, .18);

    pointer-events: auto;
}

.signature-placement-toolbar select,
.signature-placement-toolbar button {
    height: 28px;

    border: 0;
    border-radius: 5px;

    font-size: 11px;
}

.signature-placement-toolbar select {
    padding: 0 5px;
}

.signature-placement-toolbar button {
    padding: 0 8px;

    background: #dc2626;
    color: #fff;

    cursor: pointer;
}

.signature-placement-toolbar button:hover {
    background: #b91c1c;
}

/*
|--------------------------------------------------------------------------
| Responsive viewer
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .pdf-placement-viewer {
        height: 700px;
        padding: 14px;
    }

    .pdf-placement-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .pdf-viewer-help {
        width: 100%;
    }

}

@media (max-width: 600px) {

    .pdf-placement-viewer {
        height: 600px;
        padding: 8px;
    }

    .pdf-page {
        margin-bottom: 20px;
    }

}

.signature-placement-delete {
    border: none;
    background: transparent;
    color: #fff;
    cursor: pointer;
    padding: 2px 5px;
}

.signature-placement-resize {
    position: absolute;
    right: -6px;
    bottom: -6px;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    background: #2563eb;
    cursor: nwse-resize;
    z-index: 25;
}

.placement-page-number {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 4px 8px;
    background: rgba(17,24,39,.75);
    color: #fff;
    border-radius: 5px;
    font-size: 11px;
    z-index: 2;
    pointer-events: none;
}


/* ================================================================
   DIGITAL SIGNATURE SECTION
   ================================================================ */

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


/* ================================================================
   SIGNATURE TABS
   ================================================================ */

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


/* ================================================================
   SIGNATURE PANELS
   ================================================================ */

.signature-panel {
    display: none;
}

.signature-panel.active {
    display: block;
}


/* ================================================================
   DRAW CANVAS
   ================================================================ */

.signature-canvas-wrapper {
    width: 100%;
    max-width: 700px;
}

#signatureCanvas {
    display: block;
    width: 100%;
    height: 220px;
    min-height: 220px;
    background: #fff;
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


/* ================================================================
   TYPED SIGNATURE
   ================================================================ */

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


/* ================================================================
   BUTTONS
   ================================================================ */

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


/* ================================================================
   RESULT
   ================================================================ */

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


/* ================================================================
   APPLIED SIGNATURE
   ================================================================ */

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
    object-fit: contain;
}


/* ================================================================
   EXISTING SIGNED DOCUMENT
   ================================================================ */

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


/* ================================================================
   RESPONSIVE
   ================================================================ */

@media (max-width: 768px) {

    .placement-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .placement-toolbar-actions {
        width: 100%;
    }

    .placement-toolbar-actions button {
        flex: 1;
    }

    .pdf-placement-viewer {
        padding: 10px;
    }

}

</style>



<!-- ================================================================
     PAGE HEADER
     ================================================================ -->

<div class="page-header">

    <h1>
        Review Document
    </h1>

    <p>
        Review, sign and approve submitted documents.
    </p>

</div>


<!-- ================================================================
     DOCUMENT CARD
     ================================================================ -->

<div class="review-card">


    <!-- ============================================================
         DOCUMENT HEADER
         ============================================================ -->

    <div class="review-header">

        <div class="document-icon">
            <i class="fa fa-file-signature"></i>
        </div>


        <div class="document-details">

            <h2>
                <?= htmlspecialchars(
                    $document["title"]
                ) ?>
            </h2>


            <div class="document-meta">

                <span>
                    <i class="fa fa-user"></i>

                    <?= htmlspecialchars(
                        $document["uploader"] ?? "Unknown"
                    ) ?>
                </span>


                <span>
                    <i class="fa fa-calendar"></i>

                    <?= date(
                        "d M Y H:i",
                        strtotime(
                            $document["created_at"]
                        )
                    ) ?>
                </span>


                <span>
                    <i class="fa fa-layer-group"></i>

                    Version
                    <?= htmlspecialchars(
                        $document["version"] ?? "1"
                    ) ?>
                </span>


                <span
                    class="status <?= htmlspecialchars(
                        $document["status"]
                    ) ?>"
                >
                    <?= ucfirst(
                        htmlspecialchars(
                            $document["status"]
                        )
                    ) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- ============================================================
         DOCUMENT PREVIEW / ONLYOFFICE
         ============================================================ -->

    <div class="preview-card">

        <div class="preview-header">

            <h3>
                <i class="fa fa-file-pen"></i>
                Document Review &amp; Signing
            </h3>

        </div>


        <div class="onlyoffice-editor-launch">

            <div class="onlyoffice-editor-info">

                <div class="onlyoffice-editor-icon">
                    <i class="fa fa-file-signature"></i>
                </div>


                <div>

                    <strong>
                        ONLYOFFICE Document Editor
                    </strong>

                    <p>
                        Open the document in the browser to review,
                        edit and add your signature. The signature is
                        inserted directly into the document before the
                        signed PDF is generated.
                    </p>

                </div>

            </div>


            <a
                href="<?= htmlspecialchars(
                    $editorUrl,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
                class="open-editor-btn"
            >

                <i class="fa fa-pen-to-square"></i>

                Open Document for Signing

                <i class="fa fa-arrow-right"></i>

            </a>

        </div>


        <div class="editor-instructions">

            <div class="instruction-item">

                <span class="instruction-number">
                    1
                </span>

                <div>

                    <strong>
                        Open the document
                    </strong>

                    <p>
                        Open the document in ONLYOFFICE using the
                        button above.
                    </p>

                </div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    2
                </span>

                <div>

                    <strong>
                        Add your signature
                    </strong>

                    <p>
                        Use the Document Signature plugin inside
                        ONLYOFFICE to draw or type your signature and
                        insert it directly into the document.
                    </p>

                </div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    3
                </span>

                <div>

                    <strong>
                        Generate the signed copy
                    </strong>

                    <p>
                        Click <strong>Generate Signed Copy</strong>
                        in the Document Signature plugin.
                    </p>

                </div>

            </div>


            <div class="instruction-item">

                <span class="instruction-number">
                    4
                </span>

                <div>

                    <strong>
                        Return here and approve
                    </strong>

                    <p>
                        Once the signed PDF has been generated,
                        return to this page to view it and complete
                        the approval decision.
                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         EXISTING SIGNED COPY
         ============================================================ -->

    <?php if (!empty($document["reviewed_file"])): ?>

        <div class="signed-preview">

            <div>

                <h4>

                    <i class="fa fa-circle-check"></i>

                    Signed Copy Generated

                </h4>


                <p>

                    A signed PDF has already been generated for this
                    document. You can review it before approving.

                </p>

            </div>


            <div class="signed-preview-actions">

                <a
                    href="<?= htmlspecialchars(
                        $signedCopyUrl,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="signed-download"
                >

                    <i class="fa fa-file-pdf"></i>

                    View Signed PDF

                </a>


                <button
                    type="button"
                    id="removeSignedCopy"
                    class="signature-btn remove-signed-btn"
                >

                    <i class="fa fa-trash"></i>

                    Remove &amp; Re-sign

                </button>

            </div>

        </div>

    <?php else: ?>

        <div class="signed-preview signed-preview-pending">

            <div>

                <h4>

                    <i class="fa fa-clock"></i>

                    Signed Copy Not Generated

                </h4>


                <p>

                    Open the document in ONLYOFFICE, add your signature,
                    and use the Document Signature plugin to generate
                    the signed PDF.

                </p>

            </div>

        </div>

    <?php endif; ?>


</div>


<!-- ================================================================
     APPROVAL DECISION
     ================================================================ -->

<div class="action-card">

    <h3>
        Approval Decision
    </h3>


   <?php if (!empty($document["reviewed_file"])): ?>

    <p>
        Review the generated signed PDF before making the final
        approval decision.
    </p>

    <div class="review-actions">

        <button
            type="button"
            onclick="approveDocument(<?= (int)$document["id"] ?>)"
            class="approve-btn"
        >
            <i class="fa fa-check"></i>
            Approve Document
        </button>

        <button
            type="button"
            onclick="rejectDocument(<?= (int)$document["id"] ?>)"
            class="reject-btn"
        >
            <i class="fa fa-times"></i>
            Reject
        </button>

    </div>

<?php else: ?>

    <p>
        The document must be signed and a signed PDF generated
        before it can be approved.
    </p>

    <div class="review-actions">

        <button
            type="button"
            class="approve-btn"
            disabled
            title="Generate the signed copy before approving"
        >
            <i class="fa fa-lock"></i>
            Generate Signed Copy First
        </button>

        <button
            type="button"
            onclick="rejectDocument(<?= (int)$document["id"] ?>)"
            class="reject-btn"
        >
            <i class="fa fa-times"></i>
            Reject
        </button>

    </div>

<?php endif; ?>

</div>


<!-- ================================================================
     JAVASCRIPT
     ================================================================ -->

<script src="assets/js/review.js"></script>

<script src="assets/js/approvals.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| Document ID
|--------------------------------------------------------------------------
*/

const documentId =
    <?= (int)$document["id"] ?>;


/*
|--------------------------------------------------------------------------
| Remove generated signed copy
|--------------------------------------------------------------------------
*/
/* ==========================================================================
   AJAX CONFIRMATION MODAL
   ========================================================================== */

function showAjaxConfirm(message, onConfirm) {

    const overlay = document.createElement("div");

    overlay.className = "ajax-modal-overlay";

    overlay.innerHTML = `
        <div class="ajax-modal">

            <div class="ajax-modal-icon">
                <i class="fa fa-trash"></i>
            </div>

            <h3>Confirm Action</h3>

            <p>${message}</p>

            <div class="ajax-modal-actions">

                <button
                    type="button"
                    class="ajax-modal-cancel"
                    id="ajaxModalCancel"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="ajax-modal-confirm"
                    id="ajaxModalConfirm"
                >
                    Confirm
                </button>

            </div>

        </div>
    `;

    document.body.appendChild(overlay);

    const cancelButton =
        document.getElementById("ajaxModalCancel");

    const confirmButton =
        document.getElementById("ajaxModalConfirm");

    function closeModal() {
        overlay.remove();
    }

    cancelButton.addEventListener("click", function() {
        closeModal();
    });

    confirmButton.addEventListener("click", function() {

        confirmButton.disabled = true;

        confirmButton.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Processing...';

        closeModal();

        if (typeof onConfirm === "function") {
            onConfirm();
        }

    });

    overlay.addEventListener("click", function(event) {

        if (event.target === overlay) {
            closeModal();
        }

    });

}


/* ==========================================================================
   AJAX NOTIFICATION
   ========================================================================== */

function showAjaxNotification(message, type = "success") {

    const notification =
        document.createElement("div");

    notification.className =
        "ajax-notification " + type;

    const icon =
        type === "success"
            ? "fa-circle-check"
            : "fa-circle-exclamation";

    notification.innerHTML = `

        <i class="fa ${icon}"></i>

        <span>
            ${message}
        </span>

        <button
            type="button"
            class="ajax-notification-close"
            aria-label="Close"
        >
            &times;
        </button>

    `;

    document.body.appendChild(notification);

    requestAnimationFrame(function() {

        notification.classList.add("show");

    });

    const closeButton =
        notification.querySelector(
            ".ajax-notification-close"
        );

    function removeNotification() {

        notification.classList.remove("show");

        setTimeout(function() {

            notification.remove();

        }, 300);

    }

    closeButton.addEventListener(
        "click",
        removeNotification
    );

    setTimeout(
        removeNotification,
        5000
    );

}
async function removeSignedCopy() {

    showAjaxConfirm(
        "Are you sure you want to remove the generated signed copy? " +
        "You will need to sign the document again.",

        async function() {

            const button =
                document.getElementById(
                    "removeSignedCopy"
                );

            if (button) {

                button.disabled = true;

                button.innerHTML =
                    '<i class="fa fa-spinner fa-spin"></i> Removing...';

            }

            try {

                const formData =
                    new FormData();

                formData.append(
                    "id",
                    documentId
                );

                const response =
                    await fetch(
                        "api/documents/remove-signed.php",
                        {
                            method: "POST",
                            body: formData,
                            credentials: "same-origin"
                        }
                    );

                const data =
                    await response.json();

                if (!data.success) {

                    throw new Error(
                        data.message ||
                        "Unable to remove signed copy."
                    );

                }

                showAjaxNotification(
                    data.message ||
                    "Signed copy removed successfully.",
                    "success"
                );

                setTimeout(function() {

                    window.location.reload();

                }, 800);

            } catch (error) {

                console.error(
                    "Remove signed copy error:",
                    error
                );

                showAjaxNotification(
                    error.message ||
                    "Unable to remove signed copy.",
                    "error"
                );

                if (button) {

                    button.disabled = false;

                    button.innerHTML =
                        '<i class="fa fa-trash"></i> ' +
                        'Remove &amp; Re-sign';

                }

            }

        }
    );

}

/*
|--------------------------------------------------------------------------
| Bind remove signed copy button
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function() {

        const removeButton =
            document.getElementById(
                "removeSignedCopy"
            );


        if (removeButton) {

            removeButton.addEventListener(
                "click",
                removeSignedCopy
            );
        }

    }
);

</script>