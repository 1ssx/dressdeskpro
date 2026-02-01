<?php
require_once __DIR__ . '/../includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | الملف الشخصي</title>
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <?php
    $activePage = 'profile';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    
    <main class="main-content">
        <section class="profile-section">
            <h1 class="section-title">
                <i class="fas fa-user-circle"></i> الملف الشخصي
            </h1>
            
            <!-- Profile Info Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="profile-info">
                        <h2 id="profile-name">جاري التحميل...</h2>
                        <p id="profile-email" class="profile-email">جاري التحميل...</p>
                    </div>
                </div>
                
                <!-- Profile Details -->
                <div class="profile-details">
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-user"></i> الاسم الكامل
                        </span>
                        <span class="detail-value" id="detail-full-name">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-envelope"></i> البريد الإلكتروني
                        </span>
                        <span class="detail-value" id="detail-email">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-phone"></i> رقم الهاتف
                        </span>
                        <span class="detail-value" id="detail-phone">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-user-tag"></i> الدور
                        </span>
                        <span class="detail-value" id="detail-role">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-calendar-alt"></i> تاريخ الإنشاء
                        </span>
                        <span class="detail-value" id="detail-created">-</span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="profile-actions">
                    <button class="btn primary" id="edit-profile-btn">
                        <i class="fas fa-edit"></i> تعديل الملف الشخصي
                    </button>
                    <button class="btn warning" id="change-password-btn">
                        <i class="fas fa-key"></i> تغيير كلمة المرور
                    </button>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Edit Profile Modal -->
    <div id="edit-profile-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" id="close-edit-modal">&times;</span>
            <h2>تعديل الملف الشخصي</h2>
            <form id="edit-profile-form">
                <div class="form-group">
                    <label for="edit-full-name">الاسم الكامل</label>
                    <input type="text" id="edit-full-name" required>
                </div>
                <div class="form-group">
                    <label for="edit-email">البريد الإلكتروني</label>
                    <input type="email" id="edit-email" required>
                </div>
                <div class="form-group">
                    <label for="edit-phone">رقم الهاتف</label>
                    <input type="tel" id="edit-phone">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary">حفظ التغييرات</button>
                    <button type="button" class="btn secondary" id="cancel-edit-btn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="change-password-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" id="close-password-modal">&times;</span>
            <h2>تغيير كلمة المرور</h2>
            <form id="change-password-form">
                <div class="form-group">
                    <label for="current-password">كلمة المرور الحالية</label>
                    <input type="password" id="current-password" required>
                </div>
                <div class="form-group">
                    <label for="new-password">كلمة المرور الجديدة</label>
                    <input type="password" id="new-password" required minlength="8">
                    <small>يجب أن تكون 8 أحرف على الأقل</small>
                </div>
                <div class="form-group">
                    <label for="confirm-password">تأكيد كلمة المرور</label>
                    <input type="password" id="confirm-password" required minlength="8">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary">تغيير كلمة المرور</button>
                    <button type="button" class="btn secondary" id="cancel-password-btn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Core Utilities -->
    <script src="../assets/js/core/api-client.js"></script>
    <script src="../assets/js/core/formatters.js"></script>
    <script src="../assets/js/core/dom-utils.js"></script>
    <script src="../assets/js/core/validators.js"></script>
    
    <!-- Application Scripts -->
    <script src="../assets/js/profile.js"></script>
</body>
</html>

