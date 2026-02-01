<?php
/**
 * SINGLE SOURCE OF TRUTH for Database Credentials
 * ================================================
 * ALL database connections MUST use this file.
 * 
 * This file loads credentials from .env and provides them
 * in a consistent format for all database connections.
 * 
 * Usage:
 *   $creds = require __DIR__ . '/db_credentials.php';
 *   $pdo = new PDO("mysql:host={$creds['host']};...", $creds['user'], $creds['password']);
 */

// Load environment helper if not already loaded
if (!function_exists('env')) {
    require_once __DIR__ . '/../helpers/env.php';
}

// Return database credentials from environment
return [
    'host'      => env('DB_HOST', 'localhost'),
    'user'      => env('DB_USER', 'root'),
    'password'  => env('DB_PASS', ''),
    'charset'   => 'utf8mb4',
    'master_db' => env('MASTER_DB_NAME', 'wep_master'),
    'store_prefix' => env('STORE_DB_PREFIX', 'wep_store_'),
];
