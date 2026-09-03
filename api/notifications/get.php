<?php

require_once __DIR__ . '/../../Includes/auth.php';
require_once __DIR__ . '/../../Includes/notifications.php';

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user || empty($user['id'])) {

    echo json_encode([
        'success' => false,
        'count' => 0,
        'notifications' => []
    ]);

    exit;
}

$userId = (int)$user['id'];


/*
|--------------------------------------------------------------------------
| Get unread notification count
|--------------------------------------------------------------------------
*/

$countRow = fetchRow(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id=?
     AND is_read=0",
    [$userId]
);

$unreadCount = (int)($countRow['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get notifications
|--------------------------------------------------------------------------
*/

$notifications = fetchAll(
    "SELECT
        id,
        title,
        message,
        type,
        is_read,
        related_conversation_id,
        created_at
     FROM notifications
     WHERE user_id=?
     ORDER BY created_at DESC
     LIMIT 20",
    [$userId]
);


/*
|--------------------------------------------------------------------------
| Format notification time
|--------------------------------------------------------------------------
*/

foreach ($notifications as &$notification) {

    $notification['is_read'] = (int)$notification['is_read'];

    $notification['time'] = !empty($notification['created_at'])
        ? date('M j, Y g:i A', strtotime($notification['created_at']))
        : '';

}

unset($notification);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,

    // IMPORTANT:
    // This is the unread count, NOT total notifications.
    'count' => $unreadCount,

    'unread_count' => $unreadCount,

    'notifications' => $notifications
]);

exit;
