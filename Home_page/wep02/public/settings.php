<?php
require_once __DIR__ . '/../includes/session_check.php';

// Check if user is store owner
$isStoreOwner = isset($_SESSION['store_id']) && isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | الإعدادات</title>
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/settings.css">
</head>
<body>
    <?php
    $activePage = 'settings';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    
    <main class="main-content">
        <section class="settings-section">
            <h1 class="section-title">
                <i class="fas fa-cog"></i> الإعدادات
            </h1>
            
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-button active" data-tab="user-profile">
                    <i class="fas fa-user"></i> الملف الشخصي
                </button>
                <?php if ($isStoreOwner): ?>
                <button class="tab-button" data-tab="store-settings">
                    <i class="fas fa-store"></i> إعدادات المتجر
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Tab Content -->
            <div class="tab-content-wrapper">
                
                <!-- User Profile Tab -->
                <div class="tab-content active" id="user-profile">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-user-circle"></i> معلومات المستخدم</h2>
                            <p class="card-description">إدارة معلومات حسابك الشخصي</p>
                        </div>
                        
                        <div class="profile-avatar-section">
                            <div class="avatar-display">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="avatar-info">
                                <h3 id="profile-name">جاري التحميل...</h3>
                                <p id="profile-email" class="profile-email">جاري التحميل...</p>
                            </div>
                        </div>
                        
                        <div class="profile-details">
                            <div class="detail-row">
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
                            </div>
                            <div class="detail-row">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="fas fa-phone"></i> رقم الهاتف
                                    </span>
                                    <span class="detail-value" id="detail-phone">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="fas fa-calendar-alt"></i> تاريخ الإنشاء
                                    </span>
                                    <span class="detail-value" id="detail-created">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button class="btn primary" id="edit-profile-btn">
                                <i class="fas fa-edit"></i> تعديل الملف الشخصي
                            </button>
                            <button class="btn warning" id="change-password-btn">
                                <i class="fas fa-key"></i> تغيير كلمة المرور
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Store Settings Tab -->
                <?php if ($isStoreOwner): ?>
                <div class="tab-content" id="store-settings">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-store"></i> معلومات المتجر</h2>
                            <p class="card-description">إدارة اسم وشعار المتجر</p>
                        </div>
                        
                        <!-- Store Logo Section -->
                        <div class="store-logo-section">
                            <div class="logo-preview-container">
                                <div class="logo-preview" id="logo-preview">
                                    <i class="fas fa-tshirt" id="default-icon"></i>
                                    <img id="logo-image" src="" alt="Store Logo" style="display: none;">
                                </div>
                                <div class="logo-preview-info">
                                    <h3>شعار المتجر</h3>
                                    <p id="logo-status">لم يتم تحميل شعار</p>
                                </div>
                            </div>
                            
                            <div class="logo-actions">
                                <label for="logo-upload" class="btn primary upload-btn">
                                    <i class="fas fa-upload"></i> تحميل شعار
                                </label>
                                <input type="file" id="logo-upload" accept="image/png,image/jpeg,image/jpg" style="display: none;">
                                <button class="btn secondary" id="remove-logo-btn" style="display: none;">
                                    <i class="fas fa-trash"></i> إزالة الشعار
                                </button>
                            </div>
                        </div>
                        
                        <!-- Store Name Section -->
                        <div class="store-info-section">
                            <div class="form-group">
                                <label for="store-name-display">
                                    <i class="fas fa-store"></i> اسم المتجر
                                </label>
                                <div class="store-name-display-group">
                                    <input type="text" id="store-name-display" readonly>
                                    <button class="btn primary" id="edit-store-name-btn">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-note">
                            <i class="fas fa-info-circle"></i>
                            <p>سيظهر اسم وشعار المتجر في جميع صفحات النظام بما في ذلك الفواتير والتقارير.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
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
    
    <!-- Edit Store Name Modal -->
    <div id="edit-store-name-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" id="close-store-name-modal">&times;</span>
            <h2>تعديل اسم المتجر</h2>
            <form id="edit-store-name-form">
                <div class="form-group">
                    <label for="edit-store-name">اسم المتجر الجديد</label>
                    <input type="text" id="edit-store-name" required>
                    <small>سيظهر هذا الاسم في جميع صفحات النظام</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary">حفظ التغييرات</button>
                    <button type="button" class="btn secondary" id="cancel-store-name-btn">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Image Cropper Modal -->
    <div id="cropper-modal" class="cropper-modal" style="display: none;">
        <div class="cropper-modal-content">
            <span class="close" id="close-cropper-modal">&times;</span>
            <h2><i class="fas fa-crop"></i> قص الصورة</h2>
            <div class="cropper-container-wrapper">
                <img id="image-to-crop" src="" alt="Image to crop">
            </div>
            <div class="cropper-controls">
                <button type="button" class="btn secondary" id="rotate-left-btn">
                    <i class="fas fa-undo"></i> تدوير يسار
                </button>
                <button type="button" class="btn secondary" id="rotate-right-btn">
                    <i class="fas fa-redo"></i> تدوير يمين
                </button>
                <button type="button" class="btn secondary" id="flip-horizontal-btn">
                    <i class="fas fa-arrows-alt-h"></i> قلب أفقي
                </button>
                <button type="button" class="btn secondary" id="reset-crop-btn">
                    <i class="fas fa-sync"></i> إعادة تعيين
                </button>
                <button type="button" class="btn primary" id="crop-and-upload-btn">
                    <i class="fas fa-check"></i> قص ورفع
                </button>
                <button type="button" class="btn secondary" id="cancel-crop-btn">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </div>
    </div>
    
    <!-- Cropper.js Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    
    <!-- Core Utilities -->
    <script src="../assets/js/core/api-client.js"></script>
    <script src="../assets/js/core/formatters.js"></script>
    <script src="../assets/js/core/dom-utils.js"></script>
    <script src="../assets/js/core/validators.js"></script>
    
    <!-- Application Scripts -->
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/settings-cropper.js"></script>
</body>
</html>
