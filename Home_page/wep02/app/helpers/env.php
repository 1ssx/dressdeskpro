<?php
/**
 * Simple Environment Variable Loader
 * Reads .env file and provides access to environment variables
 * No external dependencies required
 */

// Global storage for environment variables
$_ENV_VARS = [];

/**
 * Load .env file from project root
 * @param string $path Path to .env file
 * @return bool True if loaded successfully
 */
function loadEnv($path = null) {
    global $_ENV_VARS;
    
    // Default to .env in project root (2 levels up from this file)
    if ($path === null) {
        $path = __DIR__ . '/../../.env';
    }
    
    // Check if file exists
    if (!file_exists($path)) {
        return false;
    }
    
    // Read file line by line
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Store in global array
            $_ENV_VARS[$key] = $value;
        }
    }
    
    return true;
}

/**
 * Get environment variable value
 * @param string $key Variable name
 * @param mixed $default Default value if not found
 * @return mixed Variable value or default
 */
function env($key, $default = null) {
    global $_ENV_VARS;
    
    // Load .env if not already loaded
    if (empty($_ENV_VARS)) {
        loadEnv();
    }
    
    return isset($_ENV_VARS[$key]) ? $_ENV_VARS[$key] : $default;
}

// Auto-load .env on first include
loadEnv();

/**
 * Configure PHP error display based on APP_DEBUG setting
 * This allows local development with visible errors while keeping production secure
 */
function configureErrorDisplay() {
    $appDebug = env('APP_DEBUG', 'false');
    $appEnv = env('APP_ENV', 'production');
    
    // Enable error display only if APP_DEBUG is explicitly true and not in production
    if (strtolower($appDebug) === 'true' && strtolower($appEnv) !== 'production') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        // Production mode - hide errors from users, log them instead
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
        ini_set('log_errors', 1);
    }
}

// Auto-configure error display
configureErrorDisplay();

