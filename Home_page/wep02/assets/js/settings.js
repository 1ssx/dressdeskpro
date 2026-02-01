/**
 * Settings Page Management
 * Handles user profile, store settings, and logo upload
 */

document.addEventListener('DOMContentLoaded', function () {
    const PROFILE_API_URL = 'api/profile.php';
    const STORE_API_URL = 'api/store_settings.php';

    // Tab Management
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    // User Profile Elements
    const editProfileBtn = document.getElementById('edit-profile-btn');
    const changePasswordBtn = document.getElementById('change-password-btn');
    const editProfileModal = document.getElementById('edit-profile-modal');
    const changePasswordModal = document.getElementById('change-password-modal');
    const editProfileForm = document.getElementById('edit-profile-form');
    const changePasswordForm = document.getElementById('change-password-form');
    const closeEditModal = document.getElementById('close-edit-modal');
    const closePasswordModal = document.getElementById('close-password-modal');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const cancelPasswordBtn = document.getElementById('cancel-password-btn');

    // Store Settings Elements
    const logoUpload = document.getElementById('logo-upload');
    const logoPreview = document.getElementById('logo-preview');
    const logoImage = document.getElementById('logo-image');
    const defaultIcon = document.getElementById('default-icon');
    const logoStatus = document.getElementById('logo-status');
    const removeLogoBtn = document.getElementById('remove-logo-btn');
    const storeNameDisplay = document.getElementById('store-name-display');
    const editStoreNameBtn = document.getElementById('edit-store-name-btn');
    const editStoreNameModal = document.getElementById('edit-store-name-modal');
    const editStoreNameForm = document.getElementById('edit-store-name-form');
    const closeStoreNameModal = document.getElementById('close-store-name-modal');
    const cancelStoreNameBtn = document.getElementById('cancel-store-name-btn');

    let currentProfile = null;
    let currentStoreLogo = null;

    // ====================
    // Tab System
    // ====================
    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            // Update active states
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');

            // Load data for the active tab
            if (targetTab === 'user-profile') {
                loadUserProfile();
            } else if (targetTab === 'store-settings') {
                loadStoreSettings();
            }
        });
    });

    // ====================
    // User Profile Management
    // ====================

    // Load profile data
    async function loadUserProfile() {
        try {
            const response = await fetch(`${PROFILE_API_URL}?action=get`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load profile');
            }

            currentProfile = result.data;
            displayProfile(currentProfile);
        } catch (error) {
            console.error('Error loading profile:', error);
            showError('فشل تحميل بيانات الملف الشخصي: ' + error.message);
        }
    }

    // Display profile data
    function displayProfile(profile) {
        document.getElementById('profile-name').textContent = profile.full_name;
        document.getElementById('profile-email').textContent = profile.email;
        document.getElementById('detail-full-name').textContent = profile.full_name;
        document.getElementById('detail-email').textContent = profile.email;
        document.getElementById('detail-phone').textContent = profile.phone || 'غير محدد';

        // Format dates
        if (profile.created_at) {
            const createdDate = new Date(profile.created_at);
            document.getElementById('detail-created').textContent = createdDate.toLocaleDateString('en-GB', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    }

    // Open edit profile modal
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function () {
            if (currentProfile) {
                document.getElementById('edit-full-name').value = currentProfile.full_name;
                document.getElementById('edit-email').value = currentProfile.email;
                document.getElementById('edit-phone').value = currentProfile.phone || '';
                editProfileModal.style.display = 'block';
            }
        });
    }

    // Handle edit profile form submission
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const fullName = document.getElementById('edit-full-name').value.trim();
            const email = document.getElementById('edit-email').value.trim();
            const phone = document.getElementById('edit-phone').value.trim();

            if (!fullName || !email) {
                showError('الاسم الكامل والبريد الإلكتروني مطلوبان');
                return;
            }

            try {
                const response = await fetch(`${PROFILE_API_URL}?action=update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: fullName,
                        email: email,
                        phone: phone || null
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to update profile');
                }

                showSuccess('تم تحديث الملف الشخصي بنجاح');
                editProfileModal.style.display = 'none';
                await loadUserProfile(); // Reload profile data
            } catch (error) {
                console.error('Error updating profile:', error);
                showError('فشل تحديث الملف الشخصي: ' + error.message);
            }
        });
    }

    // Open change password modal
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function () {
            changePasswordForm.reset();
            changePasswordModal.style.display = 'block';
        });
    }

    // Handle change password form submission
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (!currentPassword || !newPassword || !confirmPassword) {
                showError('جميع الحقول مطلوبة');
                return;
            }

            if (newPassword.length < 8) {
                showError('كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل');
                return;
            }

            if (newPassword !== confirmPassword) {
                showError('كلمة المرور الجديدة وتأكيدها غير متطابقتين');
                return;
            }

            try {
                const response = await fetch(`${PROFILE_API_URL}?action=change_password`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to change password');
                }

                showSuccess('تم تغيير كلمة المرور بنجاح');
                changePasswordModal.style.display = 'none';
                changePasswordForm.reset();
            } catch (error) {
                console.error('Error changing password:', error);
                showError('فشل تغيير كلمة المرور: ' + error.message);
            }
        });
    }

    // ====================
    // Store Settings Management
    // ====================

    // Load store settings
    async function loadStoreSettings() {
        try {
            const response = await fetch(`${STORE_API_URL}?action=get`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Failed to load store settings');
            }

            displayStoreSettings(result.data);
        } catch (error) {
            console.error('Error loading store settings:', error);
            showError('فشل تحميل إعدادات المتجر: ' + error.message);
        }
    }

    // Display store settings
    function displayStoreSettings(store) {
        if (storeNameDisplay) {
            storeNameDisplay.value = store.store_name;
        }

        if (store.logo_path) {
            currentStoreLogo = store.logo_path;
            logoImage.src = '../' + store.logo_path;
            logoImage.style.display = 'block';
            defaultIcon.style.display = 'none';
            logoStatus.textContent = 'تم تحميل الشعار';
            if (removeLogoBtn) removeLogoBtn.style.display = 'inline-flex';
        } else {
            currentStoreLogo = null;
            logoImage.style.display = 'none';
            defaultIcon.style.display = 'block';
            logoStatus.textContent = 'لم يتم تحميل شعار';
            if (removeLogoBtn) removeLogoBtn.style.display = 'none';
        }
    }

    // Handle logo upload
    if (logoUpload) {
        logoUpload.addEventListener('change', async function (e) {
            const file = e.target.files[0];
            if (!file) return;

            // Validate file type
            const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                showError('نوع الملف غير مدعوم. يرجى اختيار صورة بصيغة PNG أو JPG');
                return;
            }

            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                showError('حجم الصورة كبير جداً. الحد الأقصى 2MB');
                return;
            }

            // Upload logo
            const formData = new FormData();
            formData.append('logo', file);

            try {
                const response = await fetch(`${STORE_API_URL}?action=upload_logo`, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to upload logo');
                }

                showSuccess('تم تحميل الشعار بنجاح');
                await loadStoreSettings(); // Reload store settings

                // Reload page to update navbar logo
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } catch (error) {
                console.error('Error uploading logo:', error);
                showError('فشل تحميل الشعار: ' + error.message);
            }
        });
    }

    // Handle logo removal
    if (removeLogoBtn) {
        removeLogoBtn.addEventListener('click', async function () {
            if (!confirm('هل أنت متأكد من إزالة شعار المتجر؟')) {
                return;
            }

            try {
                const response = await fetch(`${STORE_API_URL}?action=remove_logo`, {
                    method: 'POST'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to remove logo');
                }

                showSuccess('تم إزالة الشعار بنجاح');
                await loadStoreSettings(); // Reload store settings

                // Reload page to update navbar
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } catch (error) {
                console.error('Error removing logo:', error);
                showError('فشل إزالة الشعار: ' + error.message);
            }
        });
    }

    // Open edit store name modal
    if (editStoreNameBtn) {
        editStoreNameBtn.addEventListener('click', function () {
            document.getElementById('edit-store-name').value = storeNameDisplay.value;
            editStoreNameModal.style.display = 'block';
        });
    }

    // Handle edit store name form submission
    if (editStoreNameForm) {
        editStoreNameForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const newStoreName = document.getElementById('edit-store-name').value.trim();

            if (!newStoreName) {
                showError('اسم المتجر مطلوب');
                return;
            }

            try {
                const response = await fetch(`${STORE_API_URL}?action=update_name`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        store_name: newStoreName
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to update store name');
                }

                showSuccess('تم تحديث اسم المتجر بنجاح');
                editStoreNameModal.style.display = 'none';
                await loadStoreSettings(); // Reload store settings

                // Reload page to update navbar
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } catch (error) {
                console.error('Error updating store name:', error);
                showError('فشل تحديث اسم المتجر: ' + error.message);
            }
        });
    }

    // ====================
    // Modal Controls
    // ====================

    // Close modals
    if (closeEditModal) {
        closeEditModal.addEventListener('click', function () {
            editProfileModal.style.display = 'none';
        });
    }

    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function () {
            editProfileModal.style.display = 'none';
        });
    }

    if (closePasswordModal) {
        closePasswordModal.addEventListener('click', function () {
            changePasswordModal.style.display = 'none';
        });
    }

    if (cancelPasswordBtn) {
        cancelPasswordBtn.addEventListener('click', function () {
            changePasswordModal.style.display = 'none';
        });
    }

    if (closeStoreNameModal) {
        closeStoreNameModal.addEventListener('click', function () {
            editStoreNameModal.style.display = 'none';
        });
    }

    if (cancelStoreNameBtn) {
        cancelStoreNameBtn.addEventListener('click', function () {
            editStoreNameModal.style.display = 'none';
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === editProfileModal) {
            editProfileModal.style.display = 'none';
        }
        if (event.target === changePasswordModal) {
            changePasswordModal.style.display = 'none';
        }
        if (event.target === editStoreNameModal) {
            editStoreNameModal.style.display = 'none';
        }
    });

    // ====================
    // Utility Functions
    // ====================

    function showSuccess(message) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, 'success');
        } else {
            alert(message);
        }
    }

    function showError(message) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, 'error');
        } else {
            alert(message);
        }
    }

    // ====================
    // Initialize
    // ====================

    // Load initial data for the active tab
    loadUserProfile();
});
