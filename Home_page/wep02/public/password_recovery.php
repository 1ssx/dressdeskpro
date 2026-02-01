<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور | نظام إدارة الفساتين</title>
    <meta name="description" content="استعادة كلمة المرور عبر الواتساب - نظام إدارة الفساتين">
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
                <p class="subtitle">استعادة كلمة المرور عبر الواتساب</p>

                <div class="auth-features">
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <h3>رمز عبر واتساب</h3>
                            <p>سيتم إرسال رمز التحقق إلى رقم هاتفك عبر الواتساب.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h3>آمن وموثوق</h3>
                            <p>رمز التحقق صالح لمدة 10 دقائق فقط.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h3>سريع وبسيط</h3>
                            <p>استعد كلمة مرورك في دقائق معدودة.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-container">
                <h2><i class="fas fa-key"></i> استعادة كلمة المرور</h2>
                <p class="welcome-text">أدخل اسم المتجر ورقم الهاتف المسجل لديك لتلقي رمز التحقق عبر الواتساب.</p>

                <form id="recovery-form" class="auth-form" novalidate>
                    <div class="form-group">
                        <label for="store-name">اسم المحل</label>
                        <div class="input-with-icon">
                            <i class="fas fa-store"></i>
                            <input type="text" id="store-name" name="store_name" autocomplete="organization" required 
                                   placeholder="أدخل اسم المحل">
                        </div>
                        <div class="error-message" id="store-name-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="phone-number">رقم الهاتف</label>
                        <div class="input-with-icon phone-input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone-number" name="phone" required 
                                   placeholder="مثال: 966501234567"
                                   pattern="[0-9]{10,15}"
                                   maxlength="15"
                                   inputmode="numeric">
                        </div>
                        <div class="phone-hint">
                            <i class="fas fa-info-circle"></i>
                            أدخل رقم الهاتف بالكامل مع رمز الدولة (بدون + أو 00)
                        </div>
                        <div class="error-message" id="phone-error"></div>
                    </div>

                    <button type="submit" class="auth-btn" id="send-otp-btn">
                        <i class="fas fa-paper-plane"></i>
                        إرسال رمز التحقق
                    </button>
                </form>

                <div class="auth-footer">
                    <p>تذكرت كلمة المرور؟</p>
                    <a href="login.html" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        العودة لتسجيل الدخول
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="auth-page-footer">
        <p>© 2024 نظام إدارة الفساتين. جميع الحقوق محفوظة.</p>
        <p>الإصدار 2.1.0</p>
    </footer>

    <script src="../assets/js/password-recovery.js"></script>
</body>

</html>
