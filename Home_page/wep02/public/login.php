<?php
/**
 * Multi-Store Login Handler
 * Supports authentication across multiple store databases
 * 
 * Flow:
 * 1. Accept store_name, email, password from POST
 * 2. Look up store in master database (wep_master)
 * 3. Connect to store's database dynamically
 * 4. Authenticate user in store database
 * 5. Set session variables and return JSON response
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

// Get and validate input
$storeName = isset($_POST['store_name']) ? trim($_POST['store_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($storeName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Store name is required.'
    ]);
    exit;
}

if (empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

if (empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Password is required.'
    ]);
    exit;
}

// Normalize store name (trim, remove extra spaces, normalize unicode if available)
$storeName = preg_replace('/\s+/', ' ', trim($storeName));
if (function_exists('normalizer_normalize')) {
    $storeName = normalizer_normalize($storeName, Normalizer::FORM_C);
}

try {
    // Step 1: Connect to master database
    $pdoMaster = require __DIR__ . '/../app/config/master_database.php';
    
    // Step 2: Look up store in master database
    $stmt = $pdoMaster->prepare('
        SELECT id, store_name, database_name, status 
        FROM stores 
        WHERE store_name = ? 
        LIMIT 1
    ');
    $stmt->execute([$storeName]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        echo json_encode([
            'success' => false,
            'message' => 'Store not found. Please check the store name.'
        ]);
        exit;
    }
    
    // Check if store is active
    if ($store['status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'This store account is suspended. Please contact support.'
        ]);
        exit;
    }
    
    // Step 3: Verify database exists before attempting connection
    $dbname = $store['database_name'];
    
    if (empty($dbname)) {
        error_log("Store {$store['id']} has no database_name set");
        echo json_encode([
            'success' => false,
            'message' => 'Store database is not configured. Please contact support with store name: ' . htmlspecialchars($storeName)
        ]);
        exit;
    }
    
    // Check if database exists in MySQL
    try {
        $checkDbStmt = $pdoMaster->prepare("
            SELECT SCHEMA_NAME 
            FROM INFORMATION_SCHEMA.SCHEMATA 
            WHERE SCHEMA_NAME = ?
        ");
        $checkDbStmt->execute([$dbname]);
        if (!$checkDbStmt->fetch()) {
            error_log("Database {$dbname} does not exist for store {$store['id']}");
            echo json_encode([
                'success' => false,
                'message' => 'Store database does not exist. The store may not have been fully created. Please contact support with store name: ' . htmlspecialchars($storeName)
            ]);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Failed to check database existence: " . $e->getMessage());
        // Continue anyway - might be permission issue
    }
    
    // Step 4: Connect to store's database dynamically using centralized credentials
    $dbCreds = require __DIR__ . '/../app/config/db_credentials.php';
    
    try {
        $pdoStore = new PDO(
            "mysql:host={$dbCreds['host']};dbname=$dbname;charset=utf8mb4",
            $dbCreds['user'],
            $dbCreds['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        // Database connection failed
        $errorCode = $e->getCode();
        if ($errorCode == 1049) {
            // Unknown database
            error_log("Database {$dbname} does not exist (error 1049)");
            echo json_encode([
                'success' => false,
                'message' => 'Store database does not exist. The store creation may have failed. Please contact support with store name: ' . htmlspecialchars($storeName)
            ]);
        } else {
            error_log("Store database connection failed for {$dbname}: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Unable to connect to store database. Please contact support.'
            ]);
        }
        exit;
    }
    
    // Step 5: Verify database schema is complete (check if users table exists)
    try {
        $pdoStore->query("SELECT 1 FROM users LIMIT 1");
    } catch (PDOException $e) {
        // Table doesn't exist - database is incomplete
        error_log("Users table not found in {$dbname}: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Store database is not properly configured. The database exists but the schema is incomplete. Please contact support.'
        ]);
        exit;
    }
    
    // Step 5: Query users table in store database
    $stmt = $pdoStore->prepare('
        SELECT id, full_name, email, password, role 
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Step 6: Verify password
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Email or password is incorrect.'
        ]);
        exit;
    }
    
    // Step 7: Login successful - set session variables
    session_regenerate_id(true);

    // Update last_login_at
    try {
        $updateStmt = $pdoStore->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $updateStmt->execute([$user['id']]);
    } catch (Exception $e) {
        // Log error but don't fail login
        error_log('Failed to update last_login_at: ' . $e->getMessage());
    }
    
    // Store context
    $_SESSION['store_id'] = $store['id'];
    $_SESSION['store_name'] = $store['store_name'];
    $_SESSION['store_db_name'] = $store['database_name'];
    
    // User context
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['full_name'] = $user['full_name'];  // Compatibility
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['email'] = $user['email'];  // Compatibility
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    
    // Step 8: Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => 'index.php',
        'name' => $user['full_name']
    ]);
    
} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred. Please try again later.'
    ]);
}
