<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $user = Auth::getCurrentUser();

    if (!$user || empty($user['id'])) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'count'   => 0,
            'message' => 'Not authenticated'
        ]);

        exit;
    }


    $userId = (int)$user['id'];


    $count = getUnreadNotificationCount($userId);


    echo json_encode([
        'success' => true,
        'count'   => (int)$count
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'count'   => 0,
        'message' => $e->getMessage()
    ]);

}
