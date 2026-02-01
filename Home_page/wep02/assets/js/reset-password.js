/**
 * Reset Password - Step 3: New Password
 * Handles password reset with validation
 */

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("reset-password-form");
    const newPasswordInput = document.getElementById("new-password");
    const confirmPasswordInput = document.getElementById("confirm-password");
    const resetBtn = document.getElementById("reset-btn");
    const toggleNewPassword = document.getElementById("toggle-new-password");
    const toggleConfirmPassword = document.getElementById("toggle-confirm-password");

    const passwordError = document.getElementById("password-error");
    const confirmError = document.getElementById("confirm-error");
    const passwordMatch = document.getElementById("password-match");
    const strengthFill = document.getElementById("strength-fill");
    const strengthText = document.getElementById("strength-text");

    const reqLength = document.getElementById("req-length");
    const reqLetter = document.getElementById("req-letter");
    const reqNumber = document.getElementById("req-number");

    const successModal = document.getElementById("success-modal");
    const redirectCountdown = document.getElementById("redirect-countdown");

    const API_URL = "api/password_recovery.php";

    // Check if we have reset session data
    const storeName = sessionStorage.getItem("recovery_store");
    const phone = sessionStorage.getItem("recovery_phone");
    const resetToken = sessionStorage.getItem("reset_token");

    if (!storeName || !phone || !resetToken) {
        // Redirect back to recovery page if no session
        window.location.href = "password_recovery.php";
        return;
    }

    // Toggle password visibility
    function setupTogglePassword(toggleBtn, input) {
        if (toggleBtn && input) {
            toggleBtn.addEventListener("click", () => {
                const type = input.type === "password" ? "text" : "password";
                input.type = type;
                const icon = toggleBtn.querySelector("i");
                if (icon) {
                    icon.classList.toggle("fa-eye");
                    icon.classList.toggle("fa-eye-slash");
                }
            });
        }
    }

    setupTogglePassword(toggleNewPassword, newPasswordInput);
    setupTogglePassword(toggleConfirmPassword, confirmPasswordInput);

    // Password strength calculation
    function calculatePasswordStrength(password) {
        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-zA-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        if (score <= 2) return "weak";
        if (score <= 4) return "medium";
        return "strong";
    }

    // Update password requirements
    function updateRequirements(password) {
        // Length
        if (password.length >= 8) {
            reqLength.classList.add("valid");
        } else {
            reqLength.classList.remove("valid");
        }

        // Letter
        if (/[a-zA-Z]/.test(password)) {
            reqLetter.classList.add("valid");
        } else {
            reqLetter.classList.remove("valid");
        }

        // Number
        if (/[0-9]/.test(password)) {
            reqNumber.classList.add("valid");
        } else {
            reqNumber.classList.remove("valid");
        }
    }

    // Update strength indicator
    function updateStrength(password) {
        if (!password) {
            strengthFill.className = "strength-fill";
            strengthText.className = "strength-text";
            strengthText.textContent = "قوة كلمة المرور";
            return;
        }

        const strength = calculatePasswordStrength(password);
        strengthFill.className = "strength-fill " + strength;
        strengthText.className = "strength-text " + strength;

        const texts = {
            weak: "ضعيفة",
            medium: "متوسطة",
            strong: "قوية"
        };
        strengthText.textContent = texts[strength];
    }

    // Check password match
    function checkPasswordMatch() {
        const password = newPasswordInput.value;
        const confirm = confirmPasswordInput.value;

        if (!confirm) {
            passwordMatch.classList.remove("show", "match", "no-match");
            return;
        }

        passwordMatch.classList.add("show");

        if (password === confirm) {
            passwordMatch.classList.add("match");
            passwordMatch.classList.remove("no-match");
            passwordMatch.innerHTML = '<i class="fas fa-check-circle"></i><span>كلمتا المرور متطابقتان</span>';
        } else {
            passwordMatch.classList.add("no-match");
            passwordMatch.classList.remove("match");
            passwordMatch.innerHTML = '<i class="fas fa-times-circle"></i><span>كلمتا المرور غير متطابقتين</span>';
        }
    }

    // Password input events
    newPasswordInput.addEventListener("input", () => {
        const password = newPasswordInput.value;
        updateStrength(password);
        updateRequirements(password);
        checkPasswordMatch();
        passwordError.textContent = "";
    });

    confirmPasswordInput.addEventListener("input", () => {
        checkPasswordMatch();
        confirmError.textContent = "";
    });

    // Form submission
    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Clear errors
        passwordError.textContent = "";
        confirmError.textContent = "";

        // Validate password length
        if (newPassword.length < 8) {
            passwordError.textContent = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
            newPasswordInput.focus();
            return;
        }

        // Validate password has letter
        if (!/[a-zA-Z]/.test(newPassword)) {
            passwordError.textContent = "كلمة المرور يجب أن تحتوي على حرف واحد على الأقل";
            newPasswordInput.focus();
            return;
        }

        // Validate password has number
        if (!/[0-9]/.test(newPassword)) {
            passwordError.textContent = "كلمة المرور يجب أن تحتوي على رقم واحد على الأقل";
            newPasswordInput.focus();
            return;
        }

        // Validate password match
        if (newPassword !== confirmPassword) {
            confirmError.textContent = "كلمتا المرور غير متطابقتين";
            confirmPasswordInput.focus();
            return;
        }

        // Show loading
        const originalBtnText = resetBtn.innerHTML;
        resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
        resetBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append("action", "reset_password");
            formData.append("store_name", storeName);
            formData.append("phone", phone);
            formData.append("reset_token", resetToken);
            formData.append("new_password", newPassword);

            const response = await fetch(API_URL, {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Clear session data
                sessionStorage.removeItem("recovery_store");
                sessionStorage.removeItem("recovery_phone");
                sessionStorage.removeItem("recovery_masked_phone");
                sessionStorage.removeItem("reset_token");

                // Show success modal
                successModal.style.display = "flex";

                // Start countdown
                let countdown = 5;
                const countdownInterval = setInterval(() => {
                    countdown--;
                    redirectCountdown.textContent = countdown;

                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = "login.html";
                    }
                }, 1000);
            } else {
                passwordError.textContent = data.message || "حدث خطأ أثناء حفظ كلمة المرور";
                resetBtn.innerHTML = originalBtnText;
                resetBtn.disabled = false;

                // Session expired
                if (data.session_expired) {
                    setTimeout(() => {
                        window.location.href = "password_recovery.php";
                    }, 2000);
                }
            }
        } catch (error) {
            console.error("Error:", error);
            passwordError.textContent = "حدث خطأ في الاتصال";
            resetBtn.innerHTML = originalBtnText;
            resetBtn.disabled = false;
        }
    });
});
