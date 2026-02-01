<?php
/**
 * Master Session Check
 * Verifies that master admin is logged in
 * Include at top of all master admin pages
 */

// Configure session security settings
ini_set('session.cookie_httponly', 1);
// Note: session.cookie_secure only works on HTTPS, disable for localhost
// ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if master admin is logged in
if (!isset($_SESSION['master_user_id']) || !isset($_SESSION['master_role'])) {
    // Not logged in - redirect to master login
    header('Location: master_login.php');
    exit;
}

// Check if user has owner role (only owners can access master panel)
if ($_SESSION['master_role'] !== 'owner') {
    // Not an owner - redirect to master login
    header('Location: master_login.php');
    exit;
}

// Master admin is authenticated - make data available
$currentMasterUser = [
    'id' => $_SESSION['master_user_id'],
    'name' => $_SESSION['master_user_name'] ?? '',
    'email' => $_SESSION['master_user_email'] ?? '',
    'role' => $_SESSION['master_role']
];

