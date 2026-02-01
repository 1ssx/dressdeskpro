/**
 * User Profile Management
 * Handles profile data loading, editing, and password changes
 */

document.addEventListener('DOMContentLoaded', function () {
    const API_URL = 'api/profile.php';

    // Elements
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

    let currentProfile = null;

    // Load profile data
    async function loadProfile() {
        try {
            const response = await fetch(`${API_URL}?action=get`);
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
        document.getElementById('detail-role').textContent = profile.role === 'user' ? 'مستخدم' : profile.role;

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

    // Close edit modal
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

    // Open change password modal
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', function () {
            changePasswordForm.reset();
            changePasswordModal.style.display = 'block';
        });
    }

    // Close password modal
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

    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === editProfileModal) {
            editProfileModal.style.display = 'none';
        }
        if (event.target === changePasswordModal) {
            changePasswordModal.style.display = 'none';
        }
    });

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
                const response = await fetch(`${API_URL}?action=update`, {
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
                await loadProfile(); // Reload profile data
            } catch (error) {
                console.error('Error updating profile:', error);
                showError('فشل تحديث الملف الشخصي: ' + error.message);
            }
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
                const response = await fetch(`${API_URL}?action=change_password`, {
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

    // Utility functions
    function showSuccess(message) {
        // You can use a toast library or simple alert
        alert(message);
    }

    function showError(message) {
        alert(message);
    }

    // Initial load
    loadProfile();
});

