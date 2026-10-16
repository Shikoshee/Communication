<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Return a consistent JSON response and terminate the request.
 */
function finalizeResponse(
    bool $success,
    string $message,
    array $extra = [],
    int $status = 200
): void {
    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/**
 * Resolve a relative document path inside the permanent documents folder.
 * Return null if the path is outside the permitted directory.
 */
function finalizedDocumentExists(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);

    if (
        strpos($relativePath, 'uploads/documents/') !== 0 ||
        strpos($relativePath, '..') !== false
    ) {
        return false;
    }

    $root = realpath(__DIR__ . '/../../uploads/documents');
    $file = realpath(__DIR__ . '/../../' . $relativePath);

    return $root !== false
        && $file !== false
        && strpos($file, $root . DIRECTORY_SEPARATOR) === 0
        && is_file($file)
        && filesize($file) > 0;
}

/**
 * Parse a list of positive integer IDs from a JSON array.
 */
function parseIdList($value): array
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }

    if (!is_array($value)) {
        return [];
    }

    $ids = [];

    foreach ($value as $item) {
        if (is_scalar($item)) {
            $id = filter_var($item, FILTER_VALIDATE_INT);

            if ($id !== false && $id > 0) {
                $ids[] = (int) $id;
            }
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Verify a database helper's result.
 */
function requireSuccessfulInsert($result, string $operation): int
{
    if (
        !is_array($result) ||
        empty($result['success'])
    ) {
        $error = is_array($result)
            ? (string) ($result['error'] ?? 'Unknown database error')
            : 'Unexpected database helper response';

        throw new RuntimeException($operation . ': ' . $error);
    }

    return (int) ($result['insert_id'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Request validation
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');

    finalizeResponse(
        false,
        'Only POST requests are allowed.',
        [],
        405
    );
}

$user = Auth::getCurrentUser();
$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    finalizeResponse(false, 'Your session is invalid. Please sign in again.', [], 401);
}

$draftId = (int) ($_POST['draft_id'] ?? 0);
$draftToken = trim((string) ($_POST['draft_token'] ?? ''));

if (
    $draftId <= 0 ||
    !preg_match('/^[a-f0-9]{64}$/i', $draftToken)
) {
    finalizeResponse(false, 'Invalid draft credentials.', [], 400);
}

/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
|
| The application config is expected to expose its mysqli connection as
| $conn. The transaction and database helper functions must use this same
| connection for transaction protection to work correctly.
|
*/

$conn = $GLOBALS['conn'] ?? null;

if (!$conn instanceof mysqli) {
    error_log('Finalize upload: mysqli connection $conn is unavailable.');

    finalizeResponse(
        false,
        'The database connection is unavailable.',
        [],
        500
    );
}

$finalAbsolutePath = null;
$transactionStarted = false;
$documentId = 0;

try {
    /*
    |--------------------------------------------------------------------------
    | Start transaction and lock this draft
    |--------------------------------------------------------------------------
    |
    | Concurrent requests for the same draft must wait for the first request
    | to finish. The unique documents.upload_draft_id index provides a second
    | layer of protection against duplicate document records.
    |
    */

    $conn->begin_transaction();
    $transactionStarted = true;

    $draft = fetchRow(
        "SELECT *
         FROM document_upload_drafts
         WHERE id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE",
        [$draftId, $userId]
    );

    if (
        !$draft ||
        !hash_equals(
            (string) ($draft['draft_token'] ?? ''),
            $draftToken
        )
    ) {
        throw new RuntimeException('Draft not found or access denied.');
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotency check
    |--------------------------------------------------------------------------
    |
    | If this draft already created a document, return that document rather
    | than creating another one. This also handles a retry after the first
    | request committed successfully but the browser did not receive the
    | response.
    |
    */

    $existingDocument = fetchRow(
        "SELECT id, file_path
         FROM documents
         WHERE upload_draft_id = ?
           AND uploaded_by = ?
         LIMIT 1",
        [$draftId, $userId]
    );

    if ($existingDocument) {
        $existingDocumentId = (int) $existingDocument['id'];
        $existingFilePath = (string) $existingDocument['file_path'];

        if (!finalizedDocumentExists($existingFilePath)) {
            throw new RuntimeException(
                'The document record exists, but its permanent PDF is missing or inaccessible.'
            );
        }

        if (strtolower((string) $draft['status']) !== 'finalized') {
            $updated = updateData(
                'document_upload_drafts',
                ['status' => 'finalized'],
                [
                    'id' => $draftId,
                    'user_id' => $userId
                ]
            );

            if (
                !is_array($updated) ||
                empty($updated['success'])
            ) {
                throw new RuntimeException(
                    'The existing document was found, but the draft status could not be updated.'
                );
            }
        }

        $conn->commit();
        $transactionStarted = false;

        finalizeResponse(
            true,
            'This signed document was already uploaded successfully.',
            ['document_id' => $existingDocumentId]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Draft status and expiration
    |--------------------------------------------------------------------------
    */

    if (strtolower((string) ($draft['status'] ?? '')) !== 'draft') {
        throw new RuntimeException(
            'This draft has already been finalized or is no longer available.'
        );
    }

    $expiry = strtotime((string) ($draft['expires_at'] ?? ''));

    if ($expiry === false || $expiry <= time()) {
        throw new RuntimeException('This draft has expired.');
    }

    /*
    |--------------------------------------------------------------------------
    | Read submitted metadata
    |--------------------------------------------------------------------------
    */

    $title = trim(
        (string) ($_POST['title'] ?? $draft['title'] ?? '')
    );

    $description = trim(
        (string) ($_POST['description'] ?? $draft['description'] ?? '')
    );

    $tags = trim(
        (string) ($_POST['tags'] ?? $draft['tags'] ?? '')
    );

    if (
        $title === '' ||
        (function_exists('mb_strlen')
            ? mb_strlen($title, 'UTF-8')
            : strlen($title)) > 255
    ) {
        throw new RuntimeException('Enter a valid document title.');
    }

    $approvalValue = strtolower(
        trim((string) ($_POST['approval'] ?? ''))
    );

    $approvalRequested = in_array(
        $approvalValue,
        ['1', 'on', 'true', 'yes'],
        true
    );

    /*
    |--------------------------------------------------------------------------
    | Validate departments
    |--------------------------------------------------------------------------
    */

    $departmentInput = $_POST['department_id'] ?? [];

    if (!is_array($departmentInput)) {
        $departmentInput = [];
    }

    $departmentIds = [];

    foreach ($departmentInput as $value) {
        $departmentId = filter_var($value, FILTER_VALIDATE_INT);

        if ($departmentId !== false && $departmentId > 0) {
            $departmentIds[] = (int) $departmentId;
        }
    }

    $departmentIds = array_values(array_unique($departmentIds));

    if (!$departmentIds) {
        $departmentIds = parseIdList(
            $draft['department_ids'] ?? '[]'
        );
    }

    if (!$departmentIds) {
        throw new RuntimeException('Select at least one department.');
    }

    foreach ($departmentIds as $departmentId) {
        $department = fetchRow(
            'SELECT id FROM departments WHERE id = ? LIMIT 1',
            [$departmentId]
        );

        if (!$department) {
            throw new RuntimeException(
                'A selected department is invalid.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate the signed temporary PDF
    |--------------------------------------------------------------------------
    */

    $storedPath = str_replace(
        '\\',
        '/',
        (string) ($draft['signed_file_path'] ?? '')
    );

    if (
        strpos($storedPath, 'uploads/documents/temp/') !== 0 ||
        strpos($storedPath, '..') !== false
    ) {
        throw new RuntimeException(
            'No signed PDF has been saved. Return to the editor and generate the signed copy.'
        );
    }

    $tempRoot = realpath(
        __DIR__ . '/../../uploads/documents/temp'
    );

    $signedPath = realpath(
        __DIR__ . '/../../' . $storedPath
    );

    if (
        $tempRoot === false ||
        $signedPath === false ||
        strpos(
            $signedPath,
            $tempRoot . DIRECTORY_SEPARATOR
        ) !== 0 ||
        !is_file($signedPath) ||
        !is_readable($signedPath)
    ) {
        throw new RuntimeException(
            'The saved signed PDF could not be found or accessed.'
        );
    }

    clearstatcache(true, $signedPath);

    $sourceSize = filesize($signedPath);

    if (
        $sourceSize === false ||
        $sourceSize <= 0 ||
        $sourceSize > 50 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'The signed PDF is empty or exceeds the permitted 50 MB size.'
        );
    }

    $handle = fopen($signedPath, 'rb');

    if ($handle === false) {
        throw new RuntimeException(
            'The signed PDF could not be opened.'
        );
    }

    $pdfSignature = fread($handle, 5);
    fclose($handle);

    if ($pdfSignature !== '%PDF-') {
        throw new RuntimeException(
            'The saved signed file is not a valid PDF.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare permanent storage
    |--------------------------------------------------------------------------
    */

    $documentsDirectory = __DIR__ . '/../../uploads/documents';

    if (
        !is_dir($documentsDirectory) &&
        !mkdir($documentsDirectory, 0775, true) &&
        !is_dir($documentsDirectory)
    ) {
        throw new RuntimeException(
            'Unable to create permanent document storage.'
        );
    }

    $finalName = 'signed_' .
        date('Ymd_His') . '_' .
        bin2hex(random_bytes(8)) .
        '.pdf';

    $finalAbsolutePath = $documentsDirectory .
        DIRECTORY_SEPARATOR .
        $finalName;

    $finalRelativePath = 'uploads/documents/' . $finalName;

    if (!copy($signedPath, $finalAbsolutePath)) {
        throw new RuntimeException(
            'Unable to copy the signed PDF into permanent storage.'
        );
    }

    clearstatcache(true, $finalAbsolutePath);

    $finalSize = filesize($finalAbsolutePath);

    if ($finalSize === false || $finalSize <= 0) {
        throw new RuntimeException(
            'The permanent PDF could not be verified.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create permanent document record
    |--------------------------------------------------------------------------
    |
    | upload_draft_id is the idempotency key. Its unique index ensures that
    | one draft cannot create multiple permanent document records.
    |
    */

    $documentStatus = $approvalRequested
        ? 'pending_manager_approval'
        : 'approved';

    $insertResult = insertData('documents', [
        'upload_draft_id' => $draftId,
        'title' => $title,
        'description' => $description,
        'file_name' => $finalName,
        'file_path' => $finalRelativePath,
        'file_size' => $finalSize,
        'file_type' => 'application/pdf',
        'department_id' => $departmentIds[0],
        'uploaded_by' => $userId,
        'version' => '1.0',
        'status' => $documentStatus,
        'tags' => $tags,
        'reviewed_file' => null
    ]);

    $documentId = requireSuccessfulInsert(
        $insertResult,
        'Creating the document record failed'
    );

    if ($documentId <= 0) {
        throw new RuntimeException(
            'The database did not return the new document ID.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Record the upload activity
    |--------------------------------------------------------------------------
    */

    $activityResult = insertData('activity_logs', [
        'user_id' => $userId,
        'activity' => 'Finalized signed document upload: ' . $title,
        'document_id' => $documentId,
        'department_id' => $departmentIds[0],
        'activity_type' => 'upload'
    ]);

    requireSuccessfulInsert(
        $activityResult,
        'Recording the upload activity failed'
    );

    /*
    |--------------------------------------------------------------------------
    | Build document sharing list
    |--------------------------------------------------------------------------
    */

    $shareInput = $_POST['shareUsers'] ?? [];

    if (!is_array($shareInput)) {
        $shareInput = [];
    }

    $shareIds = parseIdList($shareInput);

    $draftShareIds = parseIdList(
        $draft['share_user_ids'] ?? '[]'
    );

    $shareIds = array_values(array_unique(array_merge(
        $shareIds,
        $draftShareIds
    )));

    // Include active members of the selected departments.
    foreach ($departmentIds as $departmentId) {
        $members = fetchAll(
            "SELECT id
             FROM users
             WHERE department_id = ?
               AND status = 'active'",
            [$departmentId]
        );

        foreach ($members as $member) {
            $memberId = (int) ($member['id'] ?? 0);

            if ($memberId > 0 && $memberId !== $userId) {
                $shareIds[] = $memberId;
            }
        }
    }

    $shareIds = array_values(array_unique(array_filter(
        array_map('intval', $shareIds),
        static fn($id) => $id > 0 && $id !== $userId
    )));

    /*
    |--------------------------------------------------------------------------
    | Save sharing records
    |--------------------------------------------------------------------------
    */

    foreach ($shareIds as $shareId) {
        $recipient = fetchRow(
            "SELECT id
             FROM users
             WHERE id = ?
               AND status = 'active'
             LIMIT 1",
            [$shareId]
        );

        if (!$recipient) {
            continue;
        }

        $existingShare = fetchRow(
            "SELECT id
             FROM document_shares
             WHERE document_id = ?
               AND user_id = ?
             LIMIT 1",
            [$documentId, $shareId]
        );

        if ($existingShare) {
            continue;
        }

        $shareResult = insertData('document_shares', [
            'document_id' => $documentId,
            'user_id' => $shareId,
            'shared_by' => $userId
        ]);

        requireSuccessfulInsert(
            $shareResult,
            'Saving a document share failed'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mark the draft finalized
    |--------------------------------------------------------------------------
    */

    $updated = updateData(
        'document_upload_drafts',
        ['status' => 'finalized'],
        [
            'id' => $draftId,
            'user_id' => $userId,
            'status' => 'draft'
        ]
    );

    if (
        !is_array($updated) ||
        empty($updated['success']) ||
        (int) ($updated['affected_rows'] ?? 0) < 1
    ) {
        throw new RuntimeException(
            'The draft status could not be updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Commit all database changes
    |--------------------------------------------------------------------------
    */

    $conn->commit();
    $transactionStarted = false;

    /*
     * The temp PDF is intentionally retained for recovery. It can be deleted
     * later by a separate cleanup task once the upload workflow is verified.
     */

    finalizeResponse(
        true,
        'The signed document has been uploaded successfully.',
        [
            'document_id' => $documentId
        ]
    );

} catch (Throwable $e) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                'Finalize upload rollback failed for draft ' .
                $draftId . ': ' .
                $rollbackError->getMessage()
            );
        }
    }

    /*
     * Remove the newly copied permanent PDF if the database operation failed.
     * Never delete the temporary signed PDF: it is needed for recovery.
     */
    if (
        is_string($finalAbsolutePath) &&
        is_file($finalAbsolutePath)
    ) {
        @unlink($finalAbsolutePath);
    }

    $errorId = bin2hex(random_bytes(4));

    error_log(sprintf(
        '[%s] Finalize upload failed. Draft ID: %d; User ID: %d; Error: %s in %s:%d',
        $errorId,
        $draftId,
        $userId,
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    ));

    /*
     * If another request created the document first, return that existing
     * document as success. The unique database index makes this safe.
     */
    try {
        $existingDocument = fetchRow(
            "SELECT id, file_path
             FROM documents
             WHERE upload_draft_id = ?
               AND uploaded_by = ?
             LIMIT 1",
            [$draftId, $userId]
        );

        if (
            $existingDocument &&
            finalizedDocumentExists(
                (string) $existingDocument['file_path']
            )
        ) {
            $existingDocumentId = (int) $existingDocument['id'];

            updateData(
                'document_upload_drafts',
                ['status' => 'finalized'],
                [
                    'id' => $draftId,
                    'user_id' => $userId
                ]
            );

            finalizeResponse(
                true,
                'This signed document was already uploaded successfully.',
                ['document_id' => $existingDocumentId]
            );
        }
    } catch (Throwable $recoveryError) {
        error_log(
            'Finalize upload recovery check failed for draft ' .
            $draftId . ': ' .
            $recoveryError->getMessage()
        );
    }

    $message = 'The document could not be finalized. Please try again.';

    if (strpos($e->getMessage(), 'already been finalized') !== false) {
        $message = 'This draft has already been finalized or is no longer available.';
    } elseif (strpos($e->getMessage(), 'expired') !== false) {
        $message = 'This draft has expired. Please start a new upload.';
    } elseif (
        strpos($e->getMessage(), 'Draft not found or access denied') !== false
    ) {
        $message = 'Draft not found or access denied.';
    } elseif (
        strpos($e->getMessage(), 'signed PDF') !== false ||
        strpos($e->getMessage(), 'signed file') !== false ||
        strpos($e->getMessage(), 'permitted 50 MB') !== false
    ) {
        $message = $e->getMessage();
    }

    finalizeResponse(
        false,
        $message,
        ['error_id' => $errorId],
        500
    );
}

