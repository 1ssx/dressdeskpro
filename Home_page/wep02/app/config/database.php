<?php
/**
 * Database Configuration
 * DEPRECATED: Use app/config/store_database.php instead for multi-store support
 * This file is kept for backward compatibility only
 * 
 * For new code, use:
 *   $pdo = require __DIR__ . '/store_database.php';
 */

// Try to use store database from session (multi-store support)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load centralized database credentials
$dbCreds = require __DIR__ . '/db_credentials.php';

// Try to use store database from session
if (isset($_SESSION['store_db_name']) && !empty($_SESSION['store_db_name'])) {
    $dbname = $_SESSION['store_db_name'];
} else {
    // Fallback to default database for backward compatibility
    $dbname = 'wep02_v2';
}

try {
    $pdo = new PDO(
        "mysql:host={$dbCreds['host']};dbname=$dbname;charset={$dbCreds['charset']}",
        $dbCreds['user'],
        $dbCreds['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    return $pdo;
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}