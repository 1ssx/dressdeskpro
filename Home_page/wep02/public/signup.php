<?php
// المسار: public/signup.php
// الغرض: إنشاء حساب جديد للمستخدم

header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 2. استدعاء ملف الاتصال (موجود بجانبه في نفس المجلد)
require_once 'db.php';

// 3. استقبال البيانات
$fullName = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$phone = $_POST['phone'] ?? '';

// التحقق البسيط
if (empty($fullName) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'الرجاء ملء جميع الحقول المطلوبة.']);
    exit;
}

try {
    // 4. التحقق من عدم تكرار البريد الإلكتروني
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مسجل مسبقاً.']);
        exit;
    }

    // 5. تشفير كلمة المرور
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 6. إدخال المستخدم الجديد
    $sql = "INSERT INTO users (full_name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fullName, $email, $hashedPassword, $phone]);

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.',
        'redirect_url' => 'login.html'
    ]);

} catch (PDOException $e) {
    error_log("Signup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الحساب.']);
}
?>