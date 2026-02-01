<?php
/**
 * Store Database Connection Helper
 * Returns a PDO connection to the current store's database
 * Uses session to determine which store database to connect to
 */

// Load environment variables
require_once __DIR__ . '/../helpers/env.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if store context exists in session
if (!isset($_SESSION['store_db_name'])) {
    throw new Exception('Store database context not found in session. Please log in again.');
}

$host = env('DB_HOST', 'localhost');
$dbname = $_SESSION['store_db_name'];
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
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
        'message' => 'Store database connection failed: ' . $e->getMessage()
    ]));
}
