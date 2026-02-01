<?php
/**
 * Unified Database Connection
 * Multi-Store SaaS - Uses store database from session
 * 
 * Provides a single, consistent PDO connection to the current store's database
 * All API files should use this instead of duplicating connection logic
 */

// Prevent direct access
if (!defined('API_COMMON')) {
    die('Direct access not allowed');
}

// Start session if not already started (needed for store context)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Use store database helper (reads from session)
    $pdo = require __DIR__ . '/../../../app/config/store_database.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection failed - PDO not initialized');
    }
    
    // Ensure PDO is valid
    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection failed - invalid PDO instance');
    }
    
    // Return PDO instance
    return $pdo;
    
} catch (Exception $e) {
    // Clean any output
    $obLevel = ob_get_level();
    while (ob_get_level() > $obLevel) {
        ob_end_clean();
    }
    throw $e;
}
