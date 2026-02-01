<?php
/**
 * Master Admin Login
 * Handles login for platform owner/support staff
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Require master database
$pdoMaster = require __DIR__ . '/../../../app/config/master_database.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

if (empty($email) || empty($password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please provide email and password.'
    ]);
    exit;
}

try {
    // Query master_users table
    $stmt = $pdoMaster->prepare('SELECT id, full_name, email, password, role FROM master_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set master session variables (separate from store sessions)
        $_SESSION['master_user_id'] = $user['id'];
        $_SESSION['master_user_name'] = $user['full_name'];
        $_SESSION['master_user_email'] = $user['email'];
        $_SESSION['master_role'] = $user['role'];

        // Clear any store session variables to avoid conflicts
        unset($_SESSION['store_id'], $_SESSION['store_name'], $_SESSION['store_db_name']);
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);

        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'redirect' => 'master_dashboard.php'
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
    error_log('Master login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ]);
}

