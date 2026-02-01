<?php
/**
 * Authentication & Authorization Helper
 * PHASE 3 - API Restructuring
 * 
 * Provides session validation and user authentication
 */

// Prevent direct access
if (!defined('API_COMMON')) {
    die('Direct access not allowed');
}

/**
 * Start session if not already started
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is authenticated
 */
function requireAuth() {
    startSession();
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        sendError('Authentication required', 401);
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'user_name' => $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Unknown',
        'user_email' => $_SESSION['user_email'] ?? $_SESSION['email'] ?? null
    ];
}

/**
 * Check if user has specific role (future use)
 */
function requireRole($role) {
    $user = requireAuth();
    
    // For now, all authenticated users have access
    // Future: Check user role from database
    // $userRole = getUserRole($user['user_id']);
    // if ($userRole !== $role) {
    //     sendError('Insufficient permissions', 403);
    // }
    
    return $user;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    startSession();
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'user_name' => $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Unknown',
        'user_email' => $_SESSION['user_email'] ?? $_SESSION['email'] ?? null
    ];
}

/**
 * Get current user ID (for convenience)
 */
function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

