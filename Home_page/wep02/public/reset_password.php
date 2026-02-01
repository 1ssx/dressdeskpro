<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور | نظام إدارة الفساتين</title>
    <meta name="description" content="إعادة تعيين كلمة المرور الجديدة">
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
                <p class="subtitle">إعادة تعيين كلمة المرور</p>

                <div class="auth-features">
                    <div class="feature-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <h3>كلمة مرور قوية</h3>
                            <p>اختر كلمة مرور لا تقل عن 8 أحرف.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-check-double"></i>
                        <div>
                            <h3>تأكيد الكلمة</h3>
                            <p>تأكد من تطابق كلمتي المرور.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <h3>حماية حسابك</h3>
                            <p>استخدم أحرف وأرقام ورموز للأمان.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-container">
                <h2><i class="fas fa-key"></i> كلمة مرور جديدة</h2>
                <p class="welcome-text">أدخل كلمة المرور الجديدة لحسابك.</p>

                <form id="reset-password-form" class="auth-form" novalidate>
                    <div class="form-group">
                        <label for="new-password">كلمة المرور الجديدة</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="new-password" name="new_password" required 
                                   placeholder="أدخل كلمة المرور الجديدة"
                                   minlength="8"
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" id="toggle-new-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <span class="strength-text" id="strength-text">قوة كلمة المرور</span>
                        </div>
                        <div class="error-message" id="password-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">تأكيد كلمة المرور</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm-password" name="confirm_password" required 
                                   placeholder="أعد إدخال كلمة المرور"
                                   minlength="8"
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" id="toggle-confirm-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-match" id="password-match">
                            <i class="fas fa-times-circle"></i>
                            <span>كلمتا المرور غير متطابقتين</span>
                        </div>
                        <div class="error-message" id="confirm-error"></div>
                    </div>

                    <ul class="password-requirements">
                        <li id="req-length"><i class="fas fa-circle"></i> 8 أحرف على الأقل</li>
                        <li id="req-letter"><i class="fas fa-circle"></i> حرف واحد على الأقل</li>
                        <li id="req-number"><i class="fas fa-circle"></i> رقم واحد على الأقل</li>
                    </ul>

                    <button type="submit" class="auth-btn" id="reset-btn">
                        <i class="fas fa-save"></i>
                        حفظ كلمة المرور الجديدة
                    </button>
                </form>

                <div class="auth-footer">
                    <p>تحتاج مساعدة؟</p>
                    <a href="login.html" class="login-btn">
                        <i class="fas fa-headset"></i>
                        اتصل بالدعم الفني
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="success-modal" id="success-modal" style="display: none;">
        <div class="success-modal-content">
            <div class="success-icon-container">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>تم تغيير كلمة المرور بنجاح!</h3>
            <p>يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.</p>
            <div class="countdown-redirect">
                <span>سيتم تحويلك خلال</span>
                <span id="redirect-countdown">5</span>
                <span>ثواني</span>
            </div>
            <a href="login.html" class="auth-btn success-btn">
                <i class="fas fa-sign-in-alt"></i>
                تسجيل الدخول الآن
            </a>
        </div>
    </div>

    <footer class="auth-page-footer">
        <p>© 2024 نظام إدارة الفساتين. جميع الحقوق محفوظة.</p>
        <p>الإصدار 2.1.0</p>
    </footer>

    <script src="../assets/js/reset-password.js"></script>
</body>

</html>
