<?php
/**
 * Legacy Configuration File
 * DEPRECATED: This file is kept for backward compatibility only.
 * Multi-tenant stores should use session-based database selection via:
 *   - app/config/store_database.php (for store-specific data)
 *   - app/config/master_database.php (for master/platform data)
 */

// Load environment helper
require_once __DIR__ . '/../app/helpers/env.php';

return [
    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'name'    => env('MASTER_DB_NAME', 'wep_master'), // Default master DB - stores get their DB from session
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'timezone' => 'Asia/Riyadh',
];
