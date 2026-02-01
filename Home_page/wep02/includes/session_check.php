<?php
/**
 * Session Check - Include at top of all protected pages
 * Ensures user is logged in and store context exists, otherwise redirects to login
 */

// Configure session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    // Not logged in - redirect to login page
    header('Location: login.html');
    exit;
}

// Check if store context exists (required for multi-store system)
if (!isset($_SESSION['store_id']) || !isset($_SESSION['store_db_name']) || !isset($_SESSION['store_name'])) {
    // Store context missing - redirect to login page
    header('Location: login.html');
    exit;
}

// User is authenticated and store context exists - make data available
$currentUser = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'user'
];

$currentStore = [
    'id' => $_SESSION['store_id'],
    'name' => $_SESSION['store_name'],
    'db_name' => $_SESSION['store_db_name']
];