<?php
/**
 * Standardized JSON Response Helper
 * PHASE 3 - API Restructuring
 * 
 * Provides consistent JSON response formatting across all APIs
 */

// Prevent direct access
if (!defined('API_COMMON')) {
    die('Direct access not allowed');
}

/**
 * Send success response
 */
function sendSuccess($data = null, $message = null) {
    // Clean any output buffer multiple times to ensure it's clean
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure JSON header
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    $response = [
        'status' => 'success'
    ];
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $code = 400, $details = null) {
    // Clean any output buffer multiple times to ensure it's clean
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set response code and header
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    $response = [
        'status' => 'error',
        'message' => $message
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/**
 * Send validation error response
 */
function sendValidationError($errors) {
    sendError('Validation failed', 422, ['errors' => $errors]);
}

/**
 * Check if request method is allowed
 */
function requireMethod($allowedMethods) {
    if (!is_array($allowedMethods)) {
        $allowedMethods = [$allowedMethods];
    }
    
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
        sendError('Method not allowed', 405);
    }
}

/**
 * Get JSON input from request body
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON in request body', 400);
    }
    
    return $data ?? [];
}

/**
 * Get query parameter with default
 */
function getQueryParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Get POST parameter with default
 */
function getPostParam($key, $default = null) {
    return $_POST[$key] ?? $default;
}

