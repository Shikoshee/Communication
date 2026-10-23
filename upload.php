
<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();
$userId = (int)($user['id'] ?? 0);

$draftId = (int)($_GET['draft_id'] ?? 0);
$draftToken = trim((string)($_GET['draft_token'] ?? ''));

$draft = null;
$draftError = '';
$draftDepartments = [];
$draftShareUsers = [];
$signedPdfReady = false;

if ($draftId > 0) {
    if (!preg_match('/^[a-f0-9]{64}$/i', $draftToken)) {
        $draftError = 'The draft access link is invalid.';
    } else {
        $draft = fetchRow(
            "SELECT *
             FROM document_upload_drafts
             WHERE id = ? AND user_id = ?
             LIMIT 1",
            [$draftId, $userId]
        );

        if (!$draft ||
            !hash_equals(
                (string)$draft['draft_token'],
                $draftToken
            )) {
            $draft = null;
            $draftError = 'The draft was not found or you do not have access to it.';
        } elseif (strtolower((string)$draft['status']) !== 'draft') {
            $draftError = 'This draft is no longer available for finalization.';
            $draft = null;
        } elseif (
            strtotime((string)$draft['expires_at']) === false ||
            strtotime((string)$draft['expires_at']) <= time()
        ) {
            $draftError = 'This draft has expired. Please start a new upload.';
            $draft = null;
        } else {
            $decodedDepartments = json_decode(
                (string)$draft['department_ids'],
                true
            );

            $decodedShareUsers = json_decode(
                (string)($draft['share_user_ids'] ?? '[]'),
                true
            );

            $draftDepartments = is_array($decodedDepartments)
                ? array_map('intval', $decodedDepartments)
                : [];

            $draftShareUsers = is_array($decodedShareUsers)
                ? array_map('intval', $decodedShareUsers)
                : [];

            $relativeSignedPath = str_replace(
                '\\',
                '/',
                (string)($draft['signed_file_path'] ?? '')
            );

            $appRoot = realpath(__DIR__);

            // The signed file must be inside uploads/documents/temp.
            $tempRoot = realpath(
                __DIR__ . '/uploads/documents/temp'
            );

            if (
                $appRoot !== false &&
                $tempRoot !== false &&
                $relativeSignedPath !== '' &&
                strpos($relativeSignedPath, 'uploads/documents/temp/') === 0
            ) {
                $candidate = realpath(
                    __DIR__ . '/' . $relativeSignedPath
                );

                if (
                    $candidate !== false &&
                    strpos(
                        $candidate,
                        $tempRoot . DIRECTORY_SEPARATOR
                    ) === 0 &&
                    is_file($candidate) &&
                    filesize($candidate) > 0
                ) {
                    $handle = fopen($candidate, 'rb');
                    $signature = $handle ? fread($handle, 5) : '';
                    if ($handle) {
                        fclose($handle);
                    }

                    $signedPdfReady = ($signature === '%PDF-');
                }
            }
        }
    }
}

$departments = fetchAll(
    "SELECT id, name FROM departments ORDER BY name"
);

$users = fetchAll(
    "SELECT id,
            CONCAT(first_name, ' ', last_name) AS name,
            email
     FROM users
     WHERE status = 'active'
     ORDER BY first_name"
);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/navbar.php';

function uploadEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<link rel="stylesheet" href="assets/css/upload.css">

<div class="page-header">
    <div>
        <h1><?= $draft ? 'Finalize Document Upload' : 'Upload Document' ?></h1>
        <p>
            <?= $draft
                ? 'Review your saved document and complete the upload.'
                : 'Add new documents to the communication system.' ?>
        </p>
    </div>
</div>

<div class="upload-container">

    <?php if ($draftError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= uploadEscape($draftError) ?>
            <p>
                <a href="upload.php">Start a new upload</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($draft): ?>
        <div class="alert alert-info" role="status">
            <strong>Saved signing draft</strong>
            <p>
                Your original file is preserved. Review the signed PDF
                below, then finalize the upload.
            </p>
        </div>

        <?php if ($signedPdfReady): ?>
            <div class="signed-document-preview" style="margin-bottom: 24px;">
                <h3>Signed PDF Preview</h3>
                <p>
                    <?= uploadEscape(
                        basename((string)$draft['signed_file_path'])
                    ) ?>
                </p>

                <iframe
                    title="Signed document preview"
                    src="api/documents/draft-signed-file.php?draft_id=<?= (int)$draftId ?>&amp;draft_token=<?= rawurlencode($draftToken) ?>"
                    style="width:100%;height:650px;border:1px solid #ccc;border-radius:6px;"
                ></iframe>

                <p>
                    <a
                        href="api/documents/draft-signed-file.php?download=1&amp;draft_id=<?= (int)$draftId ?>&amp;draft_token=<?= rawurlencode($draftToken) ?>"
                        target="_blank"
                        rel="noopener"
                    >Open signed PDF in a new tab</a>
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                A valid signed PDF has not been saved for this draft yet.
                Return to the editor and generate the signed copy before
                finalizing.
                <p>
                    <a
                        href="document-editor.php?draft_id=<?= (int)$draftId ?>&amp;draft_token=<?= rawurlencode($draftToken) ?>"
                    >Return to document editor</a>
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$draftError): ?>
    <form id="uploadForm" enctype="multipart/form-data">

        <?php if ($draft): ?>
            <input type="hidden" id="draftId" value="<?= (int)$draftId ?>">
            <input type="hidden" id="draftToken" value="<?= uploadEscape($draftToken) ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="title">Document Title</label>
            <input
                type="text"
                id="title"
                name="title"
                required
                maxlength="255"
                value="<?= uploadEscape($draft['title'] ?? '') ?>"
                <?= $draft && !$signedPdfReady ? 'readonly' : '' ?>
            >
        </div>

        <div class="form-group">
            <label for="department">Departments</label>
            <select
                id="department"
                name="department_id[]"
                multiple
                required
            >
                <?php foreach ($departments as $dept): ?>
                    <option
                        value="<?= (int)$dept['id'] ?>"
                        <?= in_array((int)$dept['id'], $draftDepartments, true) ? 'selected' : '' ?>
                    >
                        <?= uploadEscape($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Hold CTRL (Windows) or CMD (Mac) to select multiple departments.</small>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5"><?= uploadEscape($draft['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="tags">Tags</label>
            <input
                type="text"
                id="tags"
                name="tags"
                value="<?= uploadEscape($draft['tags'] ?? '') ?>"
            >
        </div>

        <div class="form-group">
            <label for="documentFile">
                <?= $draft ? 'Original File' : 'Select File' ?>
            </label>

            <div class="file-upload">
                <i class="fa fa-cloud-upload"></i>
                <input
                    type="file"
                    id="documentFile"
                    name="document"
                    <?= $draft ? '' : 'required' ?>
                    <?= $draft ? 'disabled' : '' ?>
                >
            </div>

            <p id="fileName">
                <?= $draft
                    ? uploadEscape($draft['original_file_name'])
                    : '' ?>
            </p>

            <?php if ($draft): ?>
                <small>
                    The original file is already saved in this draft.
                    You do not need to select it again.
                </small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="uploadMode">How would you like to proceed?</label>
            <select id="uploadMode" name="upload_mode" required <?= $draft ? 'disabled' : '' ?>>
                <option value="normal" <?= !$draft ? 'selected' : '' ?>>Upload Normally</option>
                <option value="sign" <?= $draft ? 'selected' : '' ?>>Sign Before Submitting</option>
            </select>
            <small id="uploadModeHelp">
                <?= $draft
                    ? 'Finalize the saved signed PDF when you are ready.'
                    : 'Upload the document using the existing workflow.' ?>
            </small>
        </div>

        <div class="form-group checkbox">
            <input
                type="checkbox"
                id="approval"
                name="approval"
                <?= !empty($draft['approval_requested']) ? 'checked' : '' ?>
            >
            <label for="approval">Send for approval after upload</label>
        </div>

        <div class="form-group">
            <label for="shareUsers">Share With</label>
            <select multiple id="shareUsers" name="shareUsers[]">
                <?php foreach ($users as $shareUser): ?>
                    <option
                        value="<?= (int)$shareUser['id'] ?>"
                        <?= in_array((int)$shareUser['id'], $draftShareUsers, true) ? 'selected' : '' ?>
                    >
                        <?= uploadEscape($shareUser['name']) ?>
                        (<?= uploadEscape($shareUser['email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Hold CTRL to select multiple users.</small>
        </div>

        <div class="progress-container">
            <div id="progressBar"></div>
        </div>

        
        <?php if ($draft): ?>
            <button
                type="submit"
                class="upload-submit"
                id="finalizeUploadButton"
                <?= !$signedPdfReady ? 'disabled' : '' ?>
            >
                <i class="fa fa-check"></i>
                Finalize Upload
            </button>

            <a
                href="document-editor.php?draft_id=<?= (int)$draftId ?>&amp;draft_token=<?= rawurlencode($draftToken) ?>"
                style="display:inline-block;margin-left:12px;"
            >Return to Editor</a>

        <?php else: ?>
            <button
                type="submit"
                class="upload-submit"
                id="uploadDocumentButton"
            >
                <i class="fa fa-upload"></i>
                Upload Document
            </button>

            <button
                type="button"
                class="upload-submit"
                id="signDocumentButton"
                style="display:none;"
            >
                <i class="fa fa-pen-to-square"></i>
                Sign Document
            </button>
        <?php endif; ?>


    </form>
    <?php endif; ?>
</div>

<script src="assets/js/upload.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>