<?php
/**
 * Logout Page - Communication System
 * Handles user session termination and logging
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure user is authenticated before logging out
Auth::protect();

// Get current user info before logout
$user = Auth::getCurrentUser();

// Log the logout activity
if ($user) {
    Auth::logActivity($user['id'], 'User logged out', null, null, 'logout');
}

// Destroy session and log out
$result = Auth::logout();

// Redirect to login page
header('Location: index.php?logout=success');
exit();
?>