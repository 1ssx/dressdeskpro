// assets/js/signup.js

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('signup-form');
    if (!form) return; // حماية لو الفورم مش موجود

    const firstNameInput       = document.getElementById('first-name');
    const lastNameInput        = document.getElementById('last-name');
    const emailInput           = document.getElementById('email');
    const phoneInput           = document.getElementById('phone');
    const storeNameInput       = document.getElementById('store-name');
    const activationCodeInput  = document.getElementById('activation-code');
    const passwordInput        = document.getElementById('signup-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const termsCheckbox        = document.getElementById('terms');

    const firstNameError       = document.getElementById('first-name-error');
    const lastNameError        = document.getElementById('last-name-error');
    const emailError           = document.getElementById('email-error');
    const phoneError           = document.getElementById('phone-error');
    const storeNameError       = document.getElementById('store-name-error');
    const activationCodeError  = document.getElementById('activation-code-error');
    const passwordError        = document.getElementById('signup-password-error');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const termsError           = document.getElementById('terms-error');

    const toggleSignupPasswordBtn  = document.getElementById('toggle-signup-password');
    const toggleConfirmPasswordBtn = document.getElementById('toggle-confirm-password');

    // من منظور signup.html داخل public/
    const API_URL = './api/signup.php';

    function clearErrors() {
        if (firstNameError)       firstNameError.textContent       = '';
        if (lastNameError)        lastNameError.textContent        = '';
        if (emailError)           emailError.textContent           = '';
        if (phoneError)           phoneError.textContent           = '';
        if (storeNameError)       storeNameError.textContent       = '';
        if (activationCodeError)  activationCodeError.textContent  = '';
        if (passwordError)        passwordError.textContent        = '';
        if (confirmPasswordError) confirmPasswordError.textContent = '';
        if (termsError)           termsError.textContent           = '';
    }

    function togglePasswordVisibility(input, button) {
        if (!input || !button) return;

        const icon = button.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    if (toggleSignupPasswordBtn) {
        toggleSignupPasswordBtn.addEventListener('click', function () {
            togglePasswordVisibility(passwordInput, toggleSignupPasswordBtn);
        });
    }

    if (toggleConfirmPasswordBtn) {
        toggleConfirmPasswordBtn.addEventListener('click', function () {
            togglePasswordVisibility(confirmPasswordInput, toggleConfirmPasswordBtn);
        });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();

        const firstName = firstNameInput.value.trim();
        const lastName  = lastNameInput.value.trim();
        const email     = emailInput.value.trim();
        const phone     = phoneInput.value.trim();
        const storeName = storeNameInput.value.trim();
        const activationCode = activationCodeInput.value.trim();
        const password  = passwordInput.value;
        const confirm   = confirmPasswordInput.value;
        const termsOk   = termsCheckbox.checked;

        let hasClientError = false;

        if (!firstName) {
            firstNameError.textContent = 'الاسم الأول مطلوب.';
            hasClientError = true;
        }

        if (!lastName) {
            lastNameError.textContent = 'الاسم الأخير مطلوب.';
            hasClientError = true;
        }

        if (!email) {
            emailError.textContent = 'البريد الإلكتروني مطلوب.';
            hasClientError = true;
        }

        if (!storeName) {
            storeNameError.textContent = 'اسم المحل مطلوب.';
            hasClientError = true;
        }

        if (!activationCode) {
            activationCodeError.textContent = 'رمز التفعيل مطلوب.';
            hasClientError = true;
        }

        if (!password) {
            passwordError.textContent = 'كلمة المرور مطلوبة.';
            hasClientError = true;
        } else if (password.length < 8) {
            passwordError.textContent = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
            hasClientError = true;
        }

        if (!confirm) {
            confirmPasswordError.textContent = 'يرجى تأكيد كلمة المرور.';
            hasClientError = true;
        } else if (password !== confirm) {
            confirmPasswordError.textContent = 'كلمة المرور وتأكيدها غير متطابقين.';
            hasClientError = true;
        }

        if (!termsOk) {
            termsError.textContent = 'يجب الموافقة على الشروط والأحكام.';
            hasClientError = true;
        }

        if (hasClientError) {
            return;
        }

        const fullName = (firstName + ' ' + lastName).trim();

        const body = new URLSearchParams();
        body.append('full_name', fullName);
        body.append('email', email);
        body.append('phone', phone);
        body.append('store_name', storeName);
        body.append('activation_code', activationCode);
        body.append('password', password);
        body.append('confirm_password', confirm);
        body.append('terms', termsOk ? '1' : '');

        // Disable submit button and show loading state
        const submitBtn = document.getElementById('signup-submit-btn');
        const btnText = document.getElementById('signup-btn-text');
        const btnLoading = document.getElementById('signup-btn-loading');
        
        if (submitBtn) {
            submitBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'inline';
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            });

            // Parse JSON response
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                throw new Error('Invalid response from server. Please try again.');
            }

            if (!data.success) {
                // Re-enable button on error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (btnText) btnText.style.display = 'inline';
                    if (btnLoading) btnLoading.style.display = 'none';
                }
                
                if (data.errors) {
                    if (data.errors.full_name) {
                        if (firstNameError) firstNameError.textContent = data.errors.full_name;
                        if (lastNameError) lastNameError.textContent  = data.errors.full_name;
                    }
                    if (data.errors.email && emailError) {
                        emailError.textContent = data.errors.email;
                    }
                    if (data.errors.phone && phoneError) {
                        phoneError.textContent = data.errors.phone;
                    }
                    if (data.errors.store_name && storeNameError) {
                        storeNameError.textContent = data.errors.store_name;
                    }
                    if (data.errors.activation_code && activationCodeError) {
                        activationCodeError.textContent = data.errors.activation_code;
                    }
                    if (data.errors.password && passwordError) {
                        passwordError.textContent = data.errors.password;
                    }
                    if (data.errors.confirm_password && confirmPasswordError) {
                        confirmPasswordError.textContent = data.errors.confirm_password;
                    }
                    if (data.errors.terms && termsError) {
                        termsError.textContent = data.errors.terms;
                    }
                } else if (data.message) {
                    // Show detailed error message
                    let errorMsg = data.message;
                    if (data.error_details) {
                        errorMsg += '\n\nالتفاصيل: ' + data.error_details;
                    }
                    if (data.error_file && data.error_line) {
                        errorMsg += '\n\nالموقع: ' + data.error_file + ' (السطر ' + data.error_line + ')';
                    }
                    alert(errorMsg);
                    console.error('Signup error:', data);
                }
                return;
            }

            // Success! Hide form and show success message
            if (form) form.style.display = 'none';
            
            const successMessage = document.getElementById('signup-success-message');
            if (successMessage) {
                successMessage.style.display = 'block';
                
                // Start countdown
                let countdown = 3;
                const countdownElement = document.getElementById('countdown');
                
                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = 'login.html';
                    }
                }, 1000);
            } else {
                // Fallback if success message element not found
                alert('تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول.');
                window.location.href = 'login.html';
            }
        } catch (error) {
            console.error('Signup request failed:', error);
            
            // Re-enable button on error
            if (submitBtn) {
                submitBtn.disabled = false;
                if (btnText) btnText.style.display = 'inline';
                if (btnLoading) btnLoading.style.display = 'none';
            }
            
            alert('حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.');
        }
    });
});
