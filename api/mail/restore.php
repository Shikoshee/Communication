<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user) {

    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.'
    ]);

    exit;
}

$userId = (int)($user['id'] ?? 0);

$messageId = (int)($_POST['message_id'] ?? 0);


/*
 * Validate message ID
 */

if ($messageId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid message.'
    ]);

    exit;
}


/*
 * Make sure this message is actually
 * in the current user's trash.
 */

$trashMessage = fetchRow(

    "SELECT
        id,
        message_id,
        user_id,
        is_deleted

     FROM mail_recipients

     WHERE message_id=?
     AND user_id=?
     AND is_deleted=1

     LIMIT 1",

    [
        $messageId,
        $userId
    ]

);


if (!$trashMessage) {

    echo json_encode([
        'success' => false,
        'message' => 'Message not found in your trash.'
    ]);

    exit;
}


/*
 * Restore the message for this user.
 */

$result = executeQuery(

    "UPDATE mail_recipients

     SET
        is_deleted=0,
        deleted_at=NULL

     WHERE message_id=?
     AND user_id=?
     AND is_deleted=1",

    [
        $messageId,
        $userId
    ]

);


if (
    !is_array($result)
    ||
    empty($result['success'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to restore message.',
        'error' => $result['error'] ?? null
    ]);

    exit;
}


/*
 * Success
 */

echo json_encode([

    'success' => true,

    'message' => 'Message restored successfully.'

]);

exit;