<?php
/**
 * API Configuration Constants
 * Centralized configuration for all API endpoints
 * PHASE 3 - API Restructuring
 */

// Prevent direct access
if (!defined('API_COMMON')) {
    die('Direct access not allowed');
}

// ============================================================
// DELIVERY NOTIFICATIONS CONFIGURATION
// ============================================================

/**
 * Number of days ahead to show delivery notifications
 * Default: 7 days (includes today through 7 days from today)
 */
define('DELIVERY_ALERT_WINDOW_DAYS', 7);

/**
 * Maximum number of delivery notifications to return
 * Default: 10 (prevents UI overload)
 */
define('DELIVERY_ALERT_LIMIT', 10);

// ============================================================
// TIMEZONE CONFIGURATION
// ============================================================

/**
 * Application timezone
 * Used for date calculations and comparisons
 */
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Riyadh');
}

// Set default timezone if not already set
if (function_exists('date_default_timezone_set') && date_default_timezone_get() !== APP_TIMEZONE) {
    date_default_timezone_set(APP_TIMEZONE);
}

