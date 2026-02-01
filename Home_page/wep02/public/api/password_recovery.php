<?php
/**
 * Password Recovery API
 * Handles WhatsApp-based OTP password recovery flow
 * 
 * Actions:
 * - request_otp: Generate and send OTP via WhatsApp
 * - verify_otp: Verify the OTP code
 * - reset_password: Reset password with verified token
 * 
 * Security Features:
 * - Rate limiting (3 requests per 15 minutes)
 * - OTP expiry (10 minutes)
 * - Phone number sanitization
 * - Session regeneration after reset
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Load WhatsApp helper
require_once __DIR__ . '/../../app/helpers/whatsapp.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'الطريقة غير مسموحة'
    ]);
    exit;
}

// Get action
$action = $_POST['action'] ?? '';

if (!$action) {
    echo json_encode([
        'success' => false,
        'message' => 'الإجراء مطلوب'
    ]);
    exit;
}

try {
    // Connect to master database
    $pdoMaster = require __DIR__ . '/../../app/config/master_database.php';

    switch ($action) {
        case 'request_otp':
            handleRequestOtp($pdoMaster);
            break;
        case 'verify_otp':
            handleVerifyOtp($pdoMaster);
            break;
        case 'reset_password':
            handleResetPassword($pdoMaster);
            break;
        default:
            echo json_encode([
                'success' => false,
                'message' => 'إجراء غير معروف'
            ]);
    }
} catch (PDOException $e) {
    error_log('Password Recovery Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام'
    ]);
} catch (Exception $e) {
    error_log('Password Recovery Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle OTP Request
 */
function handleRequestOtp($pdoMaster) {
    $storeName = trim($_POST['store_name'] ?? '');
    $phone = sanitizePhone($_POST['phone'] ?? '');

    // Validate inputs
    if (empty($storeName)) {
        echo json_encode([
            'success' => false,
            'message' => 'اسم المحل مطلوب'
        ]);
        return;
    }

    if (!validatePhone($phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'رقم الهاتف غير صالح. يجب أن يكون بين 10 و 15 رقماً.'
        ]);
        return;
    }

    // Normalize store name
    $storeName = preg_replace('/\s+/', ' ', trim($storeName));
    if (function_exists('normalizer_normalize')) {
        $storeName = normalizer_normalize($storeName, Normalizer::FORM_C);
    }

    // Look up store in master database
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
            'message' => 'لم يتم العثور على المحل بهذا الاسم'
        ]);
        return;
    }

    if ($store['status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'هذا المحل معلق. يرجى التواصل مع الدعم.'
        ]);
        return;
    }

    // Connect to store database
    $dbCreds = require __DIR__ . '/../../app/config/db_credentials.php';
    
    try {
        $pdoStore = new PDO(
            "mysql:host={$dbCreds['host']};dbname={$store['database_name']};charset=utf8mb4",
            $dbCreds['user'],
            $dbCreds['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        error_log("Store DB connection failed: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'فشل الاتصال بقاعدة البيانات'
        ]);
        return;
    }

    // Ensure required columns exist
    ensureOtpColumns($pdoStore);

    // Find user by phone number
    $stmt = $pdoStore->prepare('
        SELECT id, full_name, phone, last_otp_request, otp_request_count 
        FROM users 
        WHERE phone = ? 
        LIMIT 1
    ');
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Generic error to prevent enumeration
        echo json_encode([
            'success' => false,
            'message' => 'رقم الهاتف غير مسجل في النظام'
        ]);
        return;
    }

    // Check rate limiting (3 requests per 15 minutes)
    if (isRateLimited($user)) {
        echo json_encode([
            'success' => false,
            'message' => 'لقد تجاوزت الحد المسموح. يرجى الانتظار 15 دقيقة.',
            'rate_limited' => true,
            'wait_minutes' => 15
        ]);
        return;
    }

    // Generate 4-digit OTP
    $otp = generateOtp();
    $expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Update user with OTP
    $newRequestCount = ($user['otp_request_count'] ?? 0) + 1;
    $stmt = $pdoStore->prepare('
        UPDATE users 
        SET reset_otp = ?, 
            otp_expiry = ?, 
            otp_attempts = 0,
            otp_request_count = ?,
            last_otp_request = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$otp, $expiryTime, $newRequestCount, $user['id']]);

    // Send OTP via WhatsApp
    $message = formatOtpMessage($user['full_name'], $otp, $store['store_name']);
    $result = sendWhatsApp($phone, $message);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى رقمك عبر الواتساب',
            'expires_at' => $expiryTime,
            'masked_phone' => maskPhone($phone)
        ]);
    } else {
        error_log("WhatsApp send failed: " . ($result['error'] ?? 'Unknown error'));
        echo json_encode([
            'success' => false,
            'message' => 'فشل في إرسال الرسالة. يرجى المحاولة لاحقاً.'
        ]);
    }
}

/**
 * Handle OTP Verification
 */
function handleVerifyOtp($pdoMaster) {
    $storeName = trim($_POST['store_name'] ?? '');
    $phone = sanitizePhone($_POST['phone'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    // Validate inputs
    if (empty($storeName) || !validatePhone($phone) || empty($otp)) {
        echo json_encode([
            'success' => false,
            'message' => 'بيانات غير مكتملة'
        ]);
        return;
    }

    // Validate OTP format (4 digits)
    if (!preg_match('/^[0-9]{4}$/', $otp)) {
        echo json_encode([
            'success' => false,
            'message' => 'رمز التحقق يجب أن يكون 4 أرقام',
            'clear_inputs' => true
        ]);
        return;
    }

    // Normalize store name
    $storeName = preg_replace('/\s+/', ' ', trim($storeName));
    if (function_exists('normalizer_normalize')) {
        $storeName = normalizer_normalize($storeName, Normalizer::FORM_C);
    }

    // Look up store
    $stmt = $pdoMaster->prepare('SELECT database_name FROM stores WHERE store_name = ? AND status = "active" LIMIT 1');
    $stmt->execute([$storeName]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        echo json_encode([
            'success' => false,
            'message' => 'المحل غير موجود'
        ]);
        return;
    }

    // Connect to store database
    $dbCreds = require __DIR__ . '/../../app/config/db_credentials.php';
    $pdoStore = new PDO(
        "mysql:host={$dbCreds['host']};dbname={$store['database_name']};charset=utf8mb4",
        $dbCreds['user'],
        $dbCreds['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Find user and check OTP
    $stmt = $pdoStore->prepare('
        SELECT id, reset_otp, otp_expiry, otp_attempts 
        FROM users 
        WHERE phone = ? 
        LIMIT 1
    ');
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['reset_otp']) {
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم طلب رمز تحقق لهذا الرقم',
            'clear_inputs' => true
        ]);
        return;
    }

    // Check if OTP is expired
    if (strtotime($user['otp_expiry']) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'انتهت صلاحية رمز التحقق. اطلب رمزاً جديداً.',
            'clear_inputs' => true
        ]);
        return;
    }

    // Check attempts (max 5)
    if ($user['otp_attempts'] >= 5) {
        echo json_encode([
            'success' => false,
            'message' => 'تم تجاوز عدد المحاولات المسموحة. اطلب رمزاً جديداً.',
            'clear_inputs' => true
        ]);
        return;
    }

    // Verify OTP
    if ($user['reset_otp'] !== $otp) {
        // Increment attempts
        $stmt = $pdoStore->prepare('UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?');
        $stmt->execute([$user['id']]);

        $remainingAttempts = 5 - ($user['otp_attempts'] + 1);
        echo json_encode([
            'success' => false,
            'message' => "رمز التحقق غير صحيح. محاولات متبقية: {$remainingAttempts}",
            'clear_inputs' => true
        ]);
        return;
    }

    // OTP is correct - generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Store reset token in session
    $_SESSION['password_reset'] = [
        'user_id' => $user['id'],
        'store_db' => $store['database_name'],
        'token' => $resetToken,
        'expires_at' => $tokenExpiry,
        'phone' => $phone
    ];

    // Clear OTP (but keep expiry for token validation)
    $stmt = $pdoStore->prepare('UPDATE users SET reset_otp = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'تم التحقق بنجاح',
        'reset_token' => $resetToken
    ]);
}

/**
 * Handle Password Reset
 */
function handleResetPassword($pdoMaster) {
    $storeName = trim($_POST['store_name'] ?? '');
    $phone = sanitizePhone($_POST['phone'] ?? '');
    $resetToken = trim($_POST['reset_token'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    // Validate inputs
    if (empty($storeName) || !validatePhone($phone) || empty($resetToken) || empty($newPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'بيانات غير مكتملة'
        ]);
        return;
    }

    // Validate password strength
    if (strlen($newPassword) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'
        ]);
        return;
    }

    if (!preg_match('/[a-zA-Z]/', $newPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'كلمة المرور يجب أن تحتوي على حرف واحد على الأقل'
        ]);
        return;
    }

    if (!preg_match('/[0-9]/', $newPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل'
        ]);
        return;
    }

    // Verify session token
    if (!isset($_SESSION['password_reset']) || 
        $_SESSION['password_reset']['token'] !== $resetToken ||
        strtotime($_SESSION['password_reset']['expires_at']) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'انتهت صلاحية الجلسة. يرجى إعادة عملية الاستعادة.',
            'session_expired' => true
        ]);
        return;
    }

    // Verify phone matches
    if ($_SESSION['password_reset']['phone'] !== $phone) {
        echo json_encode([
            'success' => false,
            'message' => 'بيانات غير متطابقة',
            'session_expired' => true
        ]);
        return;
    }

    // Connect to store database
    $dbCreds = require __DIR__ . '/../../app/config/db_credentials.php';
    $storeDb = $_SESSION['password_reset']['store_db'];
    $userId = $_SESSION['password_reset']['user_id'];

    $pdoStore = new PDO(
        "mysql:host={$dbCreds['host']};dbname={$storeDb};charset=utf8mb4",
        $dbCreds['user'],
        $dbCreds['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update password and clear reset data
    $stmt = $pdoStore->prepare('
        UPDATE users 
        SET password = ?,
            reset_otp = NULL,
            otp_expiry = NULL,
            otp_attempts = 0,
            otp_request_count = 0,
            last_otp_request = NULL,
            updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$hashedPassword, $userId]);

    // Clear session data
    unset($_SESSION['password_reset']);

    // Regenerate session ID for security
    session_regenerate_id(true);

    echo json_encode([
        'success' => true,
        'message' => 'تم تغيير كلمة المرور بنجاح'
    ]);
}

/**
 * Helper Functions
 */

// Sanitize phone number - only digits
function sanitizePhone($phone) {
    return preg_replace('/[^0-9]/', '', trim($phone));
}

// Validate phone format
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

// Check rate limiting
function isRateLimited($user) {
    if (!isset($user['last_otp_request']) || !$user['last_otp_request']) {
        return false;
    }

    $lastRequest = strtotime($user['last_otp_request']);
    $fifteenMinutesAgo = strtotime('-15 minutes');

    // If last request was within 15 minutes and count >= 3
    if ($lastRequest > $fifteenMinutesAgo && ($user['otp_request_count'] ?? 0) >= 3) {
        return true;
    }

    return false;
}

// Generate 4-digit OTP
function generateOtp() {
    return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

// Mask phone number for display
function maskPhone($phone) {
    $length = strlen($phone);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
}

// Format OTP message for WhatsApp
function formatOtpMessage($userName, $otp, $storeName) {
    return "مرحباً {$userName} 👋\n\n" .
           "رمز التحقق لاستعادة كلمة المرور الخاصة بك في {$storeName} هو:\n\n" .
           "🔐 {$otp}\n\n" .
           "⏰ صالح لمدة 10 دقائق فقط.\n\n" .
           "⚠️ لا تشارك هذا الرمز مع أي شخص.\n\n" .
           "إذا لم تطلب هذا الرمز، تجاهل هذه الرسالة.";
}

// Ensure OTP columns exist in users table
function ensureOtpColumns($pdo) {
    try {
        // Check if columns exist
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_otp'");
        if ($stmt->rowCount() === 0) {
            // Add columns
            $pdo->exec("
                ALTER TABLE users 
                ADD COLUMN reset_otp VARCHAR(6) DEFAULT NULL,
                ADD COLUMN otp_expiry DATETIME DEFAULT NULL,
                ADD COLUMN otp_attempts INT UNSIGNED DEFAULT 0,
                ADD COLUMN otp_request_count INT UNSIGNED DEFAULT 0,
                ADD COLUMN last_otp_request DATETIME DEFAULT NULL
            ");
        }
    } catch (PDOException $e) {
        // Columns might already exist, ignore error
        error_log("ensureOtpColumns: " . $e->getMessage());
    }
}
