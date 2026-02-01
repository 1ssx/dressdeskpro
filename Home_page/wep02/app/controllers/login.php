<?php
// app/controllers/login.php
// Multi-store login: Validates store, connects to store DB, authenticates user

session_start();
header('Content-Type: application/json; charset=utf-8');

// Require master database for store lookup
$pdoMaster = require __DIR__ . '/../config/master_database.php';

// Read POST variables
$storeName = $_POST['store_name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

if (empty($storeName) || empty($email) || empty($password)) {
    echo json_encode([
        'status' => 'error', 
        'success' => false,
        'message' => 'Please provide store name, email and password.'
    ]);
    exit;
}

try {
    // Step 1: Find store in master DB
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
            'status' => 'error',
            'success' => false,
            'message' => 'Store not found. Please check the store name.'
        ]);
        exit;
    }

    // Check if store is active
    if ($store['status'] !== 'active') {
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'This store account is suspended. Please contact support.'
        ]);
        exit;
    }

    // Step 2: Connect to store database using centralized credentials
    $dbCreds = require __DIR__ . '/../config/db_credentials.php';
    $dbname = $store['database_name'];

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

    // Step 3: Query users table in store DB
    $stmt = $pdoStore->prepare('SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session variables - store context
        $_SESSION['store_id'] = $store['id'];
        $_SESSION['store_name'] = $store['store_name'];
        $_SESSION['store_db_name'] = $store['database_name'];
        
        // Set session variables - user context
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['full_name'] = $user['full_name'];  // Compatibility
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['email'] = $user['email'];  // Compatibility
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        // Return success response
        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'name' => $user['full_name'],
            'store_name' => $store['store_name'],
            'redirect' => 'index.php'
        ]);
        exit;

    } else {
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'
        ]);
    }

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ]);
}
