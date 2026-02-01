<?php
/**
 * Global PDO connection file.
 * DEPRECATED: Use app/config/store_database.php instead for multi-store support
 * This file is kept for backward compatibility only
 * 
 * For new code, use:
 *   $pdo = require __DIR__ . '/../app/config/store_database.php';
 */

declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load centralized database credentials
$dbCreds = require __DIR__ . '/../app/config/db_credentials.php';

// Try to use store database from session (multi-store support)
if (isset($_SESSION['store_db_name']) && !empty($_SESSION['store_db_name'])) {
    $db = $_SESSION['store_db_name'];
} else {
    // Fallback to default database for backward compatibility
    $db = 'wep02_v2';
}

$dsn = "mysql:host={$dbCreds['host']};dbname=$db;charset={$dbCreds['charset']}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // Exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            // Fetch assoc arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                       // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $dbCreds['user'], $dbCreds['password'], $options);
} catch (PDOException $e) {
    // Log error and throw - API files will handle HTTP response
    error_log('Database connection failed: ' . $e->getMessage());
    throw $e;
}

return $pdo;
