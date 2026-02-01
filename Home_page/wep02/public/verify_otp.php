<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التحقق من الرمز | نظام إدارة الفساتين</title>
    <meta name="description" content="التحقق من رمز استعادة كلمة المرور">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/password-recovery.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-sidebar">
                <div class="logo">
                    <i class="fas fa-tshirt"></i>
                    <h1>متجر الفساتين</h1>
                </div>
                <p class="subtitle">التحقق من رمز الواتساب</p>

                <div class="auth-features">
                    <div class="feature-item">
                        <i class="fab fa-whatsapp"></i>
                        <div>
                            <h3>تحقق من واتساب</h3>
                            <p>تم إرسال رمز مكون من 4 أرقام إلى رقمك.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-hourglass-half"></i>
                        <div>
                            <h3>صالح لمدة محدودة</h3>
                            <p>الرمز صالح لمدة 10 دقائق فقط.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-redo-alt"></i>
                        <div>
                            <h3>إعادة الإرسال</h3>
                            <p>يمكنك طلب رمز جديد بعد انتهاء المهلة.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-container">
                <h2><i class="fas fa-shield-alt"></i> التحقق من الرمز</h2>
                <p class="welcome-text">أدخل رمز التحقق المكون من 4 أرقام الذي تم إرساله إلى رقم هاتفك عبر الواتساب.</p>

                <!-- Phone display -->
                <div class="phone-display" id="phone-display">
                    <i class="fab fa-whatsapp"></i>
                    <span id="masked-phone">-</span>
                </div>

                <form id="verify-otp-form" class="auth-form" novalidate>
                    <div class="form-group">
                        <label for="otp-input">رمز التحقق</label>
                        <div class="otp-input-container">
                            <input type="text" class="otp-digit" id="otp-1" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" autofocus>
                            <input type="text" class="otp-digit" id="otp-2" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                            <input type="text" class="otp-digit" id="otp-3" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                            <input type="text" class="otp-digit" id="otp-4" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        </div>
                        <div class="error-message" id="otp-error"></div>
                    </div>

                    <!-- Timer display -->
                    <div class="timer-container">
                        <i class="fas fa-clock"></i>
                        <span id="timer-display">10:00</span>
                        <span>دقيقة متبقية</span>
                    </div>

                    <button type="submit" class="auth-btn" id="verify-btn">
                        <i class="fas fa-check-circle"></i>
                        تحقق من الرمز
                    </button>

                    <button type="button" class="resend-btn" id="resend-btn" disabled>
                        <i class="fas fa-redo"></i>
                        إعادة إرسال الرمز
                        <span id="resend-countdown"></span>
                    </button>
                </form>

                <div class="auth-footer">
                    <p>تريد تغيير الرقم؟</p>
                    <a href="password_recovery.php" class="login-btn">
                        <i class="fas fa-arrow-right"></i>
                        العودة للخطوة السابقة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="auth-page-footer">
        <p>© 2024 نظام إدارة الفساتين. جميع الحقوق محفوظة.</p>
        <p>الإصدار 2.1.0</p>
    </footer>

    <script src="../assets/js/verify-otp.js"></script>
</body>

</html>
