<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Dashboard.php';

$user = Auth::getCurrentUser();

// ========================================
// FETCH UNREAD NOTIFICATION COUNT
// ========================================

$unreadNotificationCount = 0;

if ($user && !empty($user['id'])) {
    $unreadNotificationCount = Dashboard::getUnreadNotifications(
        $user['id']
    );
}

$firstName = $user['first_name'] ?? '';
$lastName  = $user['last_name'] ?? '';

$fullName = trim($firstName . ' ' . $lastName);

if ($fullName === '') {
    $fullName = $user['username'] ?? 'User';
}

// Generate initials
$initials = '';

foreach (preg_split('/\s+/', $fullName) as $part) {
    if ($part !== '') {
        $initials .= strtoupper($part[0]);
    }
}

$initials = substr($initials, 0, 2);

?>
<div class="main">

<div class="topbar">

<button id="mobileMenuBtn" type="button">
    <i class="fas fa-bars"></i>
</button>
    <div class="search">

        <input
            type="text"
            placeholder="Search documents...">

        <i class="fas fa-search"></i>

    </div>


    <div class="top-icons"> 

    <div class="notification-wrapper">

        <button id="notificationBell">

            <i class="fas fa-bell"></i>

            <span id="notificationCount"><?= $unreadNotificationCount ?></span>

        </button>

        <div id="notificationDropdown">

            <div class="notification-header">

Notifications

<button id="markAllRead">
Mark all read
</button>

</div>
            <div id="notificationList">
                Loading...
            </div>

           <a href="/Communication/notifications.php">
    View All Notifications
</a>


        </div>

    </div>

   <div class="profile">

    <div class="profile-avatar">
        <?= htmlspecialchars($initials) ?>
    </div>

    <span>
        <?= htmlspecialchars($fullName) ?>
    </span>

</div>

</div>
</div>