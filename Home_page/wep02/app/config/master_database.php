<?php
/**
 * Master Database Configuration
 * Centralized PDO connection to the master/platform database
 * Used for managing stores, license keys, and master admin users
 */

// Load environment variables
require_once __DIR__ . '/../helpers/env.php';

$host = env('DB_HOST', 'localhost');
$dbname = env('MASTER_DB_NAME', 'wep_master');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');

try {
    $pdoMaster = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    return $pdoMaster;
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Master database connection failed: ' . $e->getMessage()
    ]));
}
