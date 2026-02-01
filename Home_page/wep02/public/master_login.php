<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة التحكم الرئيسية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-sidebar">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                    <h1>لوحة التحكم الرئيسية</h1>
                </div>
                <p class="subtitle">نظام إدارة المنصة - للمالكين فقط</p>
                
                <div class="auth-features">
                    <div class="feature-item">
                        <i class="fas fa-store"></i>
                        <div>
                            <h3>إدارة المحلات</h3>
                            <p>عرض وإدارة جميع المحلات المسجلة في المنصة</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <i class="fas fa-key"></i>
                        <div>
                            <h3>رموز التفعيل</h3>
                            <p>إنشاء وإدارة رموز التفعيل للمحلات الجديدة</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <i class="fas fa-cog"></i>
                        <div>
                            <h3>إعدادات المنصة</h3>
                            <p>التحكم الكامل في إعدادات النظام</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="auth-form-container">
                <h2>تسجيل الدخول - المالك</h2>
                <p class="welcome-text">سجّل دخولك للوصول إلى لوحة التحكم الرئيسية.</p>
                
                <form id="master-login-form" class="auth-form" novalidate>
                    <div class="form-group">
                        <label for="master-email">البريد الإلكتروني</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="master-email" name="email" autocomplete="email" required>
                        </div>
                        <div class="error-message" id="master-email-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="master-password">كلمة المرور</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="master-password" name="password" autocomplete="current-password" required>
                            <button type="button" class="toggle-password" id="toggle-master-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="master-password-error"></div>
                    </div>

                    <button type="submit" class="auth-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer class="auth-page-footer">
        <p>© 2024 نظام إدارة المنصة. جميع الحقوق محفوظة.</p>
        <p>للمالكين والمديرين فقط</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('master-login-form');
            const emailInput = document.getElementById('master-email');
            const passwordInput = document.getElementById('master-password');
            const togglePasswordBtn = document.getElementById('toggle-master-password');
            const emailError = document.getElementById('master-email-error');
            const passwordError = document.getElementById('master-password-error');

            // Toggle password visibility
            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', function() {
                    const type = passwordInput.type === 'password' ? 'text' : 'password';
                    passwordInput.type = type;
                    const icon = togglePasswordBtn.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-eye');
                        icon.classList.toggle('fa-eye-slash');
                    }
                });
            }

            // Form submission
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    emailError.textContent = '';
                    passwordError.textContent = '';

                    const email = emailInput.value.trim();
                    const password = passwordInput.value.trim();

                    if (!email || !password) {
                        if (!email) emailError.textContent = 'البريد الإلكتروني مطلوب';
                        if (!password) passwordError.textContent = 'كلمة المرور مطلوبة';
                        return;
                    }

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحقق...';
                    submitBtn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('email', email);
                        formData.append('password', password);

                        const response = await fetch('api/master/login.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.status === 'success' || data.success === true) {
                            window.location.href = 'master_dashboard.php';
                        } else {
                            passwordError.textContent = data.message || 'بيانات الدخول غير صحيحة';
                            submitBtn.innerHTML = originalBtnText;
                            submitBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        passwordError.textContent = 'حدث خطأ في الاتصال. تأكد من تشغيل السيرفر.';
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>

