/**
 * Password Recovery - Step 1: Request OTP
 * Handles phone number input and OTP request
 */

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("recovery-form");
    const storeNameInput = document.getElementById("store-name");
    const phoneInput = document.getElementById("phone-number");
    const sendOtpBtn = document.getElementById("send-otp-btn");

    const storeNameError = document.getElementById("store-name-error");
    const phoneError = document.getElementById("phone-error");

    const API_URL = "api/password_recovery.php";

    // Phone number validation regex: 10-15 digits only
    const phoneRegex = /^[0-9]{10,15}$/;

    // Allow only numbers in phone input
    if (phoneInput) {
        phoneInput.addEventListener("input", (e) => {
            // Remove any non-numeric characters
            e.target.value = e.target.value.replace(/[^0-9]/g, "");

            // Clear error on input
            phoneError.textContent = "";
        });

        // Prevent paste of non-numeric content
        phoneInput.addEventListener("paste", (e) => {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData("text");
            const numericOnly = pastedText.replace(/[^0-9]/g, "");
            e.target.value = numericOnly.substring(0, 15);
        });
    }

    // Clear store error on input
    if (storeNameInput) {
        storeNameInput.addEventListener("input", () => {
            storeNameError.textContent = "";
        });
    }

    // Form submission
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();

            // Clear errors
            storeNameError.textContent = "";
            phoneError.textContent = "";

            const storeName = storeNameInput.value.trim();
            const phone = phoneInput.value.trim();

            // Validate store name
            if (!storeName) {
                storeNameError.textContent = "اسم المحل مطلوب";
                storeNameInput.focus();
                return;
            }

            // Validate phone number
            if (!phone) {
                phoneError.textContent = "رقم الهاتف مطلوب";
                phoneInput.focus();
                return;
            }

            if (!phoneRegex.test(phone)) {
                phoneError.textContent = "رقم الهاتف يجب أن يكون بين 10 و 15 رقماً فقط";
                phoneInput.focus();
                return;
            }

            // Show loading state
            const originalBtnText = sendOtpBtn.innerHTML;
            sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
            sendOtpBtn.disabled = true;
            sendOtpBtn.classList.add("loading");

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
                    // Store session data for next step
                    sessionStorage.setItem("recovery_store", storeName);
                    sessionStorage.setItem("recovery_phone", phone);
                    sessionStorage.setItem("recovery_expires", data.expires_at || "");
                    sessionStorage.setItem("recovery_masked_phone", data.masked_phone || phone);

                    // Show success and redirect
                    sendOtpBtn.innerHTML = '<i class="fas fa-check"></i> تم الإرسال!';

                    setTimeout(() => {
                        window.location.href = "verify_otp.php";
                    }, 1000);
                } else {
                    // Show error
                    phoneError.textContent = data.message || "حدث خطأ أثناء إرسال الرمز";
                    sendOtpBtn.innerHTML = originalBtnText;
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.classList.remove("loading");

                    // Check for rate limit
                    if (data.rate_limited) {
                        const waitTime = data.wait_minutes || 15;
                        phoneError.textContent = `تم تجاوز الحد المسموح. يرجى الانتظار ${waitTime} دقيقة.`;
                    }
                }
            } catch (error) {
                console.error("Error:", error);
                phoneError.textContent = "حدث خطأ في الاتصال. تأكد من تشغيل السيرفر.";
                sendOtpBtn.innerHTML = originalBtnText;
                sendOtpBtn.disabled = false;
                sendOtpBtn.classList.remove("loading");
            }
        });
    }
});
