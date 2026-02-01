<?php
require_once __DIR__ . '/../includes/session_check.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | إدارة العملاء</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/customers.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
</head>
<body>
    <?php
    $activePage = 'customer';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <section class="customers-header">
            <h1 class="section-title"><i class="fas fa-users"></i> إدارة العملاء</h1>
            
            <div class="customers-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي العملاء</h3>
                        <p class="stat-value" id="stat-total">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3>عملاء جدد (هذا الشهر)</h3>
                        <p class="stat-value" id="stat-new">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <h3>عملاء نشطين</h3>
                        <p class="stat-value" id="stat-active">0</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3>العملاء</h3>
                        <p class="stat-value" id="stat-vip">0</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="customers-actions">
            <button class="btn primary" id="add-customer-btn" onclick="if(typeof openAddCustomerModal === 'function') openAddCustomerModal();">
                <i class="fas fa-user-plus"></i> إضافة عميل جديد
            </button>
            
            <div class="search-filter">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="customer-search" placeholder="بحث عن عميل...">
                </div>
                
                <div class="filter-select">
                    <select id="customer-type-filter">
                        <option value="">جميع العملاء</option>
                        <option value="regular">عملاء عاديين</option>
                        <option value="vip">عملاء مميزين</option>
                        <option value="new">عملاء جدد</option>
                    </select>
                </div>
                
                <div class="filter-select">
                    <select id="customer-sort">
                        <option value="name-asc">الاسم (تصاعدي)</option>
                        <option value="name-desc">الاسم (تنازلي)</option>
                        <option value="date-asc">تاريخ التسجيل (الأقدم)</option>
                        <option value="date-desc">تاريخ التسجيل (الأحدث)</option>
                        <option value="purchases-desc">المشتريات (الأعلى)</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="customers-list">
            <div class="view-toggle">
                <button class="view-btn active" data-view="table"><i class="fas fa-list"></i> عرض قائمة</button>
                <button class="view-btn" data-view="cards"><i class="fas fa-th-large"></i> عرض بطاقات</button>
            </div>
            
            <div class="customers-table-view active">
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الاسم</th>
                            <th>رقم الهاتف 1</th>
                            <th>رقم الهاتف 2</th> <th>تاريخ التسجيل</th>
                            <!-- Removed Purchase Count -->
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="customers-table-body"></tbody>
                </table>
            </div>
            
            <div class="customers-cards-view" id="customers-cards-container">
                </div>
            
            <div class="pagination">
                <button class="pagination-btn" id="prev-page"><i class="fas fa-chevron-right"></i></button>
                <div class="pagination-numbers" id="pagination-numbers">
                    </div>
                <button class="pagination-btn" id="next-page"><i class="fas fa-chevron-left"></i></button>
            </div>
        </section>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="copyright">
                <p>© 2023 نظام إدارة محل فساتين الزفاف. جميع الحقوق محفوظة.</p>
            </div>
            <div class="version">
                <p>الإصدار 2.1.0</p>
            </div>
        </div>
    </footer>

    <div id="customer-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modal-title">إضافة عميل جديد</h2>
            <form id="customer-form">
                <input type="hidden" id="customer-id">
                <div class="form-group">
                    <label for="customer-name">الاسم الكامل:</label>
                    <input type="text" id="customer-name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-phone">رقم الهاتف 1:</label>
                        <input type="tel" id="customer-phone" required style="direction: ltr; text-align: right;">
                    </div>
                    <div class="form-group">
                        <label for="customer-phone-2">رقم الهاتف 2 (اختياري):</label>
                        <input type="tel" id="customer-phone-2" style="direction: ltr; text-align: right;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="customer-address">العنوان:</label>
                    <textarea id="customer-address"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width"> <label for="customer-type">نوع العميل:</label>
                        <select id="customer-type">
                            <option value="regular">عادي</option>
                            <option value="vip">مميز</option>
                            <option value="new">جديد</option>
                        </select>
                    </div>
                    </div>

                <div class="form-group">
                    <label for="customer-notes">ملاحظات:</label>
                    <textarea id="customer-notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn secondary" id="cancel-btn">إلغاء</button>
                    <button type="submit" class="btn primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>

    <div id="customer-details-modal" class="modal">
        <div class="modal-content details-modal">
            <span class="close">&times;</span>
            <div class="customer-details-header">
                <div class="customer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="customer-basic-info">
                    <h2 id="detail-customer-name"></h2>
                    <p id="detail-customer-type" class="customer-type"></p>
                </div>
            </div>
            <div class="customer-details-content">
                <div class="details-section">
                    <h3><i class="fas fa-info-circle"></i> معلومات الاتصال</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">رقم الهاتف 1:</span>
                            <span id="detail-customer-phone" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">رقم الهاتف 2:</span>
                            <span id="detail-customer-phone-2" class="detail-value"></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">العنوان:</span>
                            <span id="detail-customer-address" class="detail-value"></span>
                        </div>
                        
                        </div>
                </div>
                
                <div class="details-section">
                    <h3><i class="fas fa-shopping-bag"></i> المشتريات والحجوزات</h3>
                    <div class="purchases-list" id="customer-purchases">
                        </div>
                </div>
                
                <div class="details-section">
                    <h3><i class="fas fa-sticky-note"></i> ملاحظات</h3>
                    <p id="detail-customer-notes" class="customer-notes"></p>
                </div>
            </div>
            <div class="customer-details-actions">
                <button class="btn warning" id="edit-customer-btn">
                    <i class="fas fa-edit"></i> تعديل
                </button>
                <button class="btn success" id="add-purchase-btn">
                    <i class="fas fa-plus-circle"></i> إضافة مشتريات
                </button>
                <button class="btn danger" id="delete-customer-btn">
                    <i class="fas fa-trash"></i> حذف
                </button>
            </div>
        </div>
    </div>

    <div id="delete-confirm-modal" class="modal">
        <div class="modal-content confirm-modal">
            <h2><i class="fas fa-exclamation-triangle"></i> تأكيد الحذف</h2>
            <p>هل أنت متأكد من حذف هذا العميل؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="confirm-actions">
                <button class="btn secondary" id="cancel-delete-btn">إلغاء</button>
                <button class="btn danger" id="confirm-delete-btn">تأكيد الحذف</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/customers.js"></script>
    
</body>
</html>