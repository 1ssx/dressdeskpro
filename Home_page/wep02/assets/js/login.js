// login.js - التعامل مع تسجيل الدخول

document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.getElementById("login-form");
    const storeNameInput = document.getElementById("store-name");
    const emailInput = document.getElementById("login-email");
    const passwordInput = document.getElementById("login-password");
    const togglePasswordBtn = document.getElementById("toggle-login-password");
    
    // عناصر عرض الأخطاء
    const storeNameError = document.getElementById("store-name-error");
    const emailError = document.getElementById("login-email-error");
    const passwordError = document.getElementById("login-password-error");

    // الرابط المباشر لأن login.php بجانب login.html
    const LOGIN_URL = "api/login.php";

    // --- إظهار/إخفاء كلمة المرور ---
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener("click", () => {
            const type = passwordInput.type === "password" ? "text" : "password";
            passwordInput.type = type;
            
            const icon = togglePasswordBtn.querySelector("i");
            if (icon) {
                icon.classList.toggle("fa-eye");
                icon.classList.toggle("fa-eye-slash");
            }
        });
    }

    // --- معالجة الإرسال ---
    if (loginForm) {
        loginForm.addEventListener("submit", async (e) => {
            e.preventDefault(); // <--- هذا السطر هو الأهم لمنع ظهور النص الخام

            // تصفير الأخطاء
            storeNameError.textContent = "";
            emailError.textContent = "";
            passwordError.textContent = "";

            const storeName = storeNameInput.value.trim();
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();

            if (!storeName || !email || !password) {
                if (!storeName) storeNameError.textContent = "اسم المحل مطلوب";
                if (!email) emailError.textContent = "البريد الإلكتروني مطلوب";
                if (!password) passwordError.textContent = "كلمة المرور مطلوبة";
                return;
            }

            // تغيير الزر لحالة التحميل
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحقق...';
            submitBtn.disabled = true;

            const formData = new FormData();
            formData.append('store_name', storeName);
            formData.append('email', email);
            formData.append('password', password);

            try {
                const response = await fetch(LOGIN_URL, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success' || data.success === true) {
                    // 1. تخزين الاسم
                    if (data.name) {
                        localStorage.setItem("userName", data.name);
                    }
                    
                    // Store store name in sessionStorage for invoice printing
                    if (data.store_name) {
                        sessionStorage.setItem("store_name", data.store_name);
                    }
                    
                    // 2. التوجيه (نتأكد من إزالة الشرطة المائلة الزائدة إن وجدت)
                    let redirectUrl = data.redirect || data.redirect_url || 'index.php';

                    // إصلاح المسار إذا جاء من السيرفر بـ / في البداية لتجنب الذهاب لـ localhost root
                    if (redirectUrl.startsWith('/') || redirectUrl.startsWith('\\')) {
                        redirectUrl = redirectUrl.substring(1);
                    }
                    
                    window.location.href = redirectUrl;
                } else {
                    passwordError.textContent = data.message || "بيانات الدخول غير صحيحة";
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }

            } catch (error) {
                console.error('Error:', error);
                passwordError.textContent = "حدث خطأ في الاتصال. تأكد من تشغيل السيرفر.";
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
        });
    }
});
