/**
 * Verify OTP - Step 2: OTP Verification
 * Handles OTP input and verification
 */

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("verify-otp-form");
    const otpInputs = document.querySelectorAll(".otp-digit");
    const verifyBtn = document.getElementById("verify-btn");
    const resendBtn = document.getElementById("resend-btn");
    const timerDisplay = document.getElementById("timer-display");
    const timerContainer = document.querySelector(".timer-container");
    const otpError = document.getElementById("otp-error");
    const maskedPhoneDisplay = document.getElementById("masked-phone");
    const resendCountdown = document.getElementById("resend-countdown");

    const API_URL = "api/password_recovery.php";

    // Check if we have recovery session data
    const storeName = sessionStorage.getItem("recovery_store");
    const phone = sessionStorage.getItem("recovery_phone");
    const maskedPhone = sessionStorage.getItem("recovery_masked_phone");

    if (!storeName || !phone) {
        // Redirect back to recovery page if no session
        window.location.href = "password_recovery.php";
        return;
    }

    // Display masked phone
    if (maskedPhoneDisplay && maskedPhone) {
        maskedPhoneDisplay.textContent = maskedPhone;
    }

    // Timer variables
    let timerSeconds = 600; // 10 minutes
    let timerInterval = null;
    let resendTimerSeconds = 60; // 60 seconds before resend is allowed
    let resendInterval = null;

    // Start the countdown timer
    function startTimer() {
        timerInterval = setInterval(() => {
            timerSeconds--;
            updateTimerDisplay();

            if (timerSeconds <= 0) {
                clearInterval(timerInterval);
                timerContainer.classList.add("expired");
                timerDisplay.textContent = "انتهى!";
                otpError.textContent = "انتهت صلاحية الرمز. اطلب رمزاً جديداً.";
                verifyBtn.disabled = true;
            }
        }, 1000);
    }

    // Update timer display
    function updateTimerDisplay() {
        const minutes = Math.floor(timerSeconds / 60);
        const seconds = timerSeconds % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
    }

    // Start resend countdown
    function startResendTimer() {
        resendBtn.disabled = true;
        resendInterval = setInterval(() => {
            resendTimerSeconds--;
            resendCountdown.textContent = `(${resendTimerSeconds}ث)`;

            if (resendTimerSeconds <= 0) {
                clearInterval(resendInterval);
                resendBtn.disabled = false;
                resendCountdown.textContent = "";
            }
        }, 1000);
    }

    // Initialize timers
    updateTimerDisplay();
    startTimer();
    startResendTimer();

    // OTP Input handling
    otpInputs.forEach((input, index) => {
        // Only allow numbers
        input.addEventListener("input", (e) => {
            const value = e.target.value.replace(/[^0-9]/g, "");
            e.target.value = value;

            if (value) {
                input.classList.add("filled");
                // Move to next input
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            } else {
                input.classList.remove("filled");
            }

            // Clear error
            otpError.textContent = "";
            otpInputs.forEach(inp => inp.classList.remove("error"));

            // Auto-submit when all filled
            const allFilled = Array.from(otpInputs).every(inp => inp.value.length === 1);
            if (allFilled) {
                form.dispatchEvent(new Event("submit"));
            }
        });

        // Handle backspace
        input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !input.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });

        // Handle paste
        input.addEventListener("paste", (e) => {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData("text");
            const digits = pastedData.replace(/[^0-9]/g, "").split("");

            digits.forEach((digit, i) => {
                if (otpInputs[i]) {
                    otpInputs[i].value = digit;
                    otpInputs[i].classList.add("filled");
                }
            });

            // Focus on last filled or next empty
            const lastIndex = Math.min(digits.length, otpInputs.length) - 1;
            if (lastIndex >= 0 && lastIndex < otpInputs.length - 1) {
                otpInputs[lastIndex + 1].focus();
            }
        });
    });

    // Get OTP value
    function getOtpValue() {
        return Array.from(otpInputs).map(input => input.value).join("");
    }

    // Form submission
    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const otp = getOtpValue();

        if (otp.length !== 4) {
            otpError.textContent = "الرجاء إدخال الرمز كاملاً";
            otpInputs.forEach(input => input.classList.add("error"));
            return;
        }

        // Show loading
        const originalBtnText = verifyBtn.innerHTML;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحقق...';
        verifyBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append("action", "verify_otp");
            formData.append("store_name", storeName);
            formData.append("phone", phone);
            formData.append("otp", otp);

            const response = await fetch(API_URL, {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Store reset token for next step
                sessionStorage.setItem("reset_token", data.reset_token);

                verifyBtn.innerHTML = '<i class="fas fa-check"></i> تم التحقق!';

                setTimeout(() => {
                    window.location.href = "reset_password.php";
                }, 1000);
            } else {
                otpError.textContent = data.message || "رمز غير صحيح";
                otpInputs.forEach(input => input.classList.add("error"));
                verifyBtn.innerHTML = originalBtnText;
                verifyBtn.disabled = false;

                // Clear inputs on error
                if (data.clear_inputs) {
                    otpInputs.forEach(input => {
                        input.value = "";
                        input.classList.remove("filled");
                    });
                    otpInputs[0].focus();
                }
            }
        } catch (error) {
            console.error("Error:", error);
            otpError.textContent = "حدث خطأ في الاتصال";
            verifyBtn.innerHTML = originalBtnText;
            verifyBtn.disabled = false;
        }
    });

    // Resend button
    resendBtn.addEventListener("click", async () => {
        if (resendBtn.disabled) return;

        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
        resendBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append("action", "request_otp");
            formData.append("store_name", storeName);
            formData.append("phone", phone);

            const response = await fetch(API_URL, {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Reset timer
                timerSeconds = 600;
                updateTimerDisplay();
                timerContainer.classList.remove("expired");
                verifyBtn.disabled = false;

                // Clear inputs
                otpInputs.forEach(input => {
                    input.value = "";
                    input.classList.remove("filled", "error");
                });
                otpInputs[0].focus();
                otpError.textContent = "";

                // Reset resend timer
                resendTimerSeconds = 60;
                resendBtn.innerHTML = '<i class="fas fa-redo"></i> إعادة إرسال الرمز';
                startResendTimer();

                // Show success message
                otpError.style.color = "#28a745";
                otpError.textContent = "تم إرسال رمز جديد بنجاح!";
                setTimeout(() => {
                    otpError.style.color = "";
                    otpError.textContent = "";
                }, 3000);
            } else {
                otpError.textContent = data.message || "فشل في إرسال الرمز";
                resendBtn.innerHTML = '<i class="fas fa-redo"></i> إعادة إرسال الرمز';
                resendBtn.disabled = false;
            }
        } catch (error) {
            console.error("Error:", error);
            otpError.textContent = "حدث خطأ في الاتصال";
            resendBtn.innerHTML = '<i class="fas fa-redo"></i> إعادة إرسال الرمز';
            resendBtn.disabled = false;
        }
    });
});
