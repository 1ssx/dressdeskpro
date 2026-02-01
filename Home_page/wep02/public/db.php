<?php
// db.php
// DEPRECATED: This file is kept for backward compatibility only
// New code should use app/config/store_database.php instead
// This file will try to use store database from session, or fall back to wep02_v2

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load centralized database credentials
$dbCreds = require __DIR__ . '/../app/config/db_credentials.php';

// Try to use store database from session (multi-store support)
if (isset($_SESSION['store_db_name']) && !empty($_SESSION['store_db_name'])) {
    $db_name = $_SESSION['store_db_name'];
} else {
    // Fallback to default database for backward compatibility
    // This should only be used for legacy code or test scripts
    $db_name = 'wep02_v2';
}

// إعداد خيارات الاتصال (DSN)
$dsn = "mysql:host={$dbCreds['host']};dbname=$db_name;charset={$dbCreds['charset']}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // تفعيل رمي الاستثناءات عند حدوث خطأ
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // إرجاع النتائج كمصفوفة ترابطية
    PDO::ATTR_EMULATE_PREPARES   => false,                  // استخدام التحضير الحقيقي (حماية أفضل)
];

try {
    // إنشاء الاتصال
    $pdo = new PDO($dsn, $dbCreds['user'], $dbCreds['password'], $options);
} catch (\PDOException $e) {
    // في حال فشل الاتصال، نرمي استثناء بدلاً من طباعة JSON مباشرة
    // هذا يسمح لـ database.php بالتعامل مع الخطأ بشكل صحيح
    throw new Exception('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
}
?>