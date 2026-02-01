<?php
/**
 * API Common Bootstrap
 * PHASE 3 - API Restructuring
 * 
 * Loads all common infrastructure files
 * Include this at the top of all API files
 */

// Disable error display to prevent HTML output in JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering IMMEDIATELY to catch any output
if (!ob_get_level()) {
    ob_start();
}

// Define constant to prevent direct access to common files
if (!defined('API_COMMON')) {
    define('API_COMMON', true);
}

// Set default headers FIRST (before any output)
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(204);
    exit;
}

// Load common files (order matters - response.php needed for sendError)
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
// Load config constants (after API_COMMON is defined)
require_once __DIR__ . '/config.php';

// Get PDO connection (database.php returns PDO)
try {
    $pdo = require __DIR__ . '/database.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO connection not initialized');
    }
} catch (Exception $e) {
    // Clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    sendError('Database connection failed: ' . $e->getMessage(), 500);
}

// Clean any accidental output before API logic runs
// Clean multiple times to ensure no output leaks
$obLevel = ob_get_level();
if ($obLevel > 0) {
    $output = ob_get_contents();
    if (!empty($output)) {
        // Log any unexpected output for debugging
        if (function_exists('logError')) {
            logError('Unexpected output before API logic', ['output' => substr($output, 0, 200)]);
        }
    }
    ob_clean();
}

