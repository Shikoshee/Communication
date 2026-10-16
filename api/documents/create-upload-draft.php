
<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json; charset=utf-8');

$user = Auth::getCurrentUser();

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function draftResponse(bool $success, string $message, array $extra = []): void
{
    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| REQUEST VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    draftResponse(false, 'Only POST requests are allowed.');
}

if (
    !isset($_FILES['document']) ||
    !is_array($_FILES['document']) ||
    $_FILES['document']['error'] !== UPLOAD_ERR_OK
) {
    draftResponse(false, 'Please select a valid document to upload.');
}

/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$tags = trim($_POST['tags'] ?? '');

$approvalRequested =
    isset($_POST['approval']) &&
    in_array(
        strtolower((string) $_POST['approval']),
        ['on', '1', 'true', 'yes'],
        true
    );

/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = $_POST['department_id'] ?? [];

if (!is_array($departments)) {
    $departments = [$departments];
}

$departments = array_values(array_unique(array_filter(
    array_map('intval', $departments),
    static fn($id) => $id > 0
)));

if ($title === '') {
    draftResponse(false, 'Document title is required.');
}

if (empty($departments)) {
    draftResponse(false, 'Please select at least one department.');
}

/*
|--------------------------------------------------------------------------
| VERIFY SELECTED DEPARTMENTS
|--------------------------------------------------------------------------
*/

$placeholders = implode(',', array_fill(0, count($departments), '?'));

$validDepartments = fetchAll(
    "SELECT id
     FROM departments
     WHERE id IN ($placeholders)",
    $departments
);

$validDepartmentIds = array_map(
    'intval',
    array_column($validDepartments, 'id')
);

$departments = array_values(array_intersect(
    $departments,
    $validDepartmentIds
));

if (empty($departments)) {
    draftResponse(false, 'The selected departments could not be verified.');
}

/*
|--------------------------------------------------------------------------
| USERS SELECTED FOR SHARING
|--------------------------------------------------------------------------
*/

$shareUsers = $_POST['shareUsers'] ?? [];

if (!is_array($shareUsers)) {
    $shareUsers = [$shareUsers];
}

$shareUsers = array_values(array_unique(array_filter(
    array_map('intval', $shareUsers),
    static fn($id) => $id > 0
)));

/*
|--------------------------------------------------------------------------
| AUTOMATICALLY INCLUDE ACTIVE DEPARTMENT MEMBERS
|--------------------------------------------------------------------------
*/

$departmentPlaceholders = implode(
    ',',
    array_fill(0, count($departments), '?')
);

$departmentUsers = fetchAll(
    "SELECT id
     FROM users
     WHERE department_id IN ($departmentPlaceholders)
       AND status = 'active'",
    $departments
);

foreach ($departmentUsers as $departmentUser) {
    $shareUsers[] = (int) $departmentUser['id'];
}

$shareUsers = array_values(array_unique(array_filter(
    array_map('intval', $shareUsers),
    static fn($id) => $id > 0 && $id !== (int) $user['id']
)));

/*
|--------------------------------------------------------------------------
| FILE VALIDATION
|--------------------------------------------------------------------------
*/

$file = $_FILES['document'];

$maxFileSize = defined('MAX_FILE_SIZE')
    ? (int) MAX_FILE_SIZE
    : 50 * 1024 * 1024;

if ((int) $file['size'] <= 0) {
    draftResponse(false, 'The selected file is empty.');
}

if ((int) $file['size'] > $maxFileSize) {
    draftResponse(false, 'The file exceeds the maximum permitted upload size.');
}

$originalName = basename(
    str_replace('\\', '/', (string) $file['name'])
);

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowedExtensions = [
    'doc',
    'docx',
    'docm',
    'dot',
    'dotx',
    'odt',
    'rtf',
    'txt'
];

if (!in_array($extension, $allowedExtensions, true)) {
    draftResponse(
        false,
        'For this signing workflow, upload a Word-compatible document: DOC, DOCX, DOCM, DOT, DOTX, ODT, RTF or TXT.'
    );
}

/*
|--------------------------------------------------------------------------
| TEMPORARY STORAGE
|--------------------------------------------------------------------------
*/

$projectRoot = dirname(__DIR__, 2);

$tempDirectory = $projectRoot . DIRECTORY_SEPARATOR
    . 'uploads' . DIRECTORY_SEPARATOR
    . 'documents' . DIRECTORY_SEPARATOR
    . 'temp';

if (
    !is_dir($tempDirectory) &&
    !mkdir($tempDirectory, 0750, true) &&
    !is_dir($tempDirectory)
) {
    draftResponse(false, 'Could not create the temporary upload directory.');
}

/*
|--------------------------------------------------------------------------
| CREATE A RANDOM, PRIVATE DRAFT TOKEN
|--------------------------------------------------------------------------
*/

$draftToken = bin2hex(random_bytes(32));

$storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;

$absoluteFilePath = $tempDirectory . DIRECTORY_SEPARATOR . $storedFilename;

$relativeFilePath = 'uploads/documents/temp/' . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $absoluteFilePath)) {
    draftResponse(false, 'Failed to save the temporary document.');
}

/*
|--------------------------------------------------------------------------
| SAVE DRAFT METADATA
|--------------------------------------------------------------------------
*/

$primaryDepartment = (int) $departments[0];

$result = insertData(
    'document_upload_drafts',
    [
        'draft_token' => $draftToken,
        'user_id' => (int) $user['id'],

        'title' => $title,
        'description' => $description,
        'tags' => $tags,

        'original_file_name' => $originalName,
        'original_file_path' => $relativeFilePath,
        'signed_file_path' => null,

        'file_size' => (int) $file['size'],
        'file_type' => $file['type'] ?? '',

        'department_ids' => json_encode(
            $departments,
            JSON_THROW_ON_ERROR
        ),

        'share_user_ids' => json_encode(
            $shareUsers,
            JSON_THROW_ON_ERROR
        ),

        'approval_requested' => $approvalRequested ? 1 : 0,
        'status' => 'draft',

        'expires_at' => date('Y-m-d H:i:s', time() + 86400)
    ]
);

if (empty($result['success'])) {
    if (is_file($absoluteFilePath)) {
        unlink($absoluteFilePath);
    }

    error_log(
        'Draft creation failed: ' .
        ($result['error'] ?? 'Unknown database error')
    );

    draftResponse(false, 'Could not create the temporary draft.');
}

$draftId = (int) ($result['insert_id'] ?? 0);

if ($draftId <= 0) {
    if (is_file($absoluteFilePath)) {
        unlink($absoluteFilePath);
    }

    draftResponse(
        false,
        'The draft was saved, but its ID could not be retrieved.'
    );
}

/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
|
| No row is inserted into documents here.
| No approval notifications are sent here.
| No document shares or activity logs are created here.
|
| These happen only when the user finalizes the upload.
|--------------------------------------------------------------------------
*/

draftResponse(
    true,
    'Temporary draft created. The document is ready for the signing stage.',
    [
        'draft_id' => $draftId,
        'draft_token' => $draftToken,
        'original_file_name' => $originalName,
        'expires_in_hours' => 24
    ]
);

