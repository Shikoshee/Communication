<?php

require_once "includes/config.php";
require_once "includes/auth.php";
require_once "includes/notifications.php";

Auth::protect();

$user = Auth::getCurrentUser();

$notifications = fetchAll(
    "
    SELECT
        id,
        title,
        message,
        type,
        related_document_id,
        related_conversation_id,
        is_read,
        created_at
    FROM notifications
    WHERE user_id=?
    ORDER BY created_at DESC
    ",
    [
        $user['id']
    ]
);

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";
?>

<link rel="stylesheet" href="assets/css/notifications.css">

<div class="page-header">

    <h1>Notifications</h1>

    <p>Manage all your notifications.</p>

</div>

<div class="notification-toolbar">

    <button id="markAllRead" class="btn-primary">

        <i class="fa fa-check-double"></i>

        Mark All Read

    </button>

</div>

<div id="notificationContainer">

<?php if (empty($notifications)): ?>

    <div class="empty-state">

        <i class="fa fa-bell-slash"></i>

        <h3>No Notifications</h3>

        <p>You don't have any notifications yet.</p>

    </div>

<?php else: ?>

<?php foreach ($notifications as $notification): ?>

<div
    class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>"

    data-id="<?= (int)$notification['id'] ?>"

    data-type="<?= htmlspecialchars($notification['type']) ?>"

    data-document="<?= (int)$notification['related_document_id'] ?>"

    data-conversation="<?= (int)$notification['related_conversation_id'] ?>"
>

    <div class="notification-left">

        <i class="fa fa-bell"></i>

    </div>

    <div class="notification-middle">

        <h3>

            <?= htmlspecialchars($notification['title']) ?>

        </h3>

        <p>

            <?= htmlspecialchars($notification['message']) ?>

        </p>

        <small>

            <?= date("d M Y H:i", strtotime($notification['created_at'])) ?>

        </small>

    </div>

    <div class="notification-right">

        <button
            class="mark-read"
            data-id="<?= (int)$notification['id'] ?>"
            title="Mark as read"
        >

            <i class="fa fa-check"></i>

        </button>

        <button
            class="delete-notification"
            data-id="<?= (int)$notification['id'] ?>"
            title="Delete notification"
        >

            <i class="fa fa-trash"></i>

        </button>

    </div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

<script src="assets/js/notifications-page.js"></script>

<?php include "includes/footer.php"; ?>