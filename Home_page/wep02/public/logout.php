<?php
/**
 * Logout Handler
 * Destroys session and redirects to appropriate login page
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is a master admin logout
$isMasterLogout = isset($_SESSION['master_user_id']);

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to appropriate login page
if ($isMasterLogout) {
    header('Location: master_login.php');
} else {
    header('Location: login.html');
}
exit;