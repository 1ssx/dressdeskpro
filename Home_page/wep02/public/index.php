<?php
// Check authentication
require_once __DIR__ . '/../includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | الصفحة الرئيسية</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="apple-touch-icon" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/search.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
</head>
<body>
    <?php
    $activePage = 'index';
    $showDeliveryBadge = true; // Enable delivery badge for index page
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">

        <!-- New Luxury Top Bar -->
        <div class="dashboard-top-bar">
            <!-- Right Side: Greeting -->
            <div class="greeting-block">
                <?php 
                $welcomeName = isset($currentUser['name']) ? explode(' ', $currentUser['name'])[0] : 'مدير النظام';
                // Fallback if name is empty
                if(empty($welcomeName)) $welcomeName = 'مدير النظام';
                ?>
                <h1 class="greeting-title">صباح الخير، <?php echo htmlspecialchars($welcomeName); ?></h1>
                <p class="greeting-subtext">إليك ملخص أداء البوتيك لهذا اليوم</p>
            </div>

            <!-- Center: Search Bar -->
            <div class="top-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="بحث عن فاتورة أو عميل..." id="global-search-input">
            </div>

            <!-- Left Side: New Invoice Action -->
            <div class="top-actions">
                <a href="new-invoice.php" class="btn-new-invoice-luxury">
                    <i class="fas fa-plus"></i> فاتورة جديدة
                </a>
            </div>
        </div>

        <div class="page-content active" id="dashboard-page">
            <section class="dashboard">
                <!-- Old title removed -->
                
                <div class="overview-cards">
                    <div class="card sales-card">
                        <div class="card-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="card-content">
                            <h3>المبيعات اليومية</h3>
                            <p class="amount">...</p>
                            <p class="change">...</p>
                        </div>
                    </div>
                    
                    <div class="card revenue-card">
                        <div class="card-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="card-content">
                            <h3>إجمالي الإيرادات</h3>
                            <p class="amount">...</p>
                            <p class="change">عربونات اليوم</p>
                        </div>
                    </div>
                    
                    <div class="card customers-card">
                        <div class="card-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="card-content">
                            <h3>عملاء جدد</h3>
                            <p class="amount">...</p>
                            <p class="month-total">...</p>
                        </div>
                    </div>
                    
                    <div class="card invoices-card">
                        <div class="card-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="card-content">
                            <h3>عدد الفواتير</h3>
                            <p class="amount">...</p>
                            <p class="change">فواتير اليوم</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <section class="notifications-section">
                <h2 class="section-title"><i class="fas fa-bell"></i> الإشعارات والتنبيهات</h2>
                <div id="notifications-container" class="notifications-container">
                    <div class="notification-item notification-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>جاري تحميل الإشعارات...</p>
                    </div>
                </div>
            </section>
            
            <section class="quick-actions">
                <h2 class="section-title"><i class="fas fa-bolt"></i> إجراءات سريعة</h2>
                <div class="action-buttons">
                    <button class="btn primary" id="add-dress-btn"><i class="fas fa-plus"></i> إضافة فستان جديد</button>
                    <a href="new-invoice.php" style="text-decoration: none;">
                        <button class="btn success">
                            <i class="fas fa-file-invoice"></i> إنشاء فاتورة جديدة
                        </button>
                    </a>
                    <button class="btn warning" id="add-customer-btn"><i class="fas fa-user-plus"></i> إضافة عميل جديد</button>
                </div>
            </section>
            
            <section class="recent-activities">
                <h2 class="section-title"><i class="fas fa-history"></i> النشاطات الأخيرة</h2>
                <div class="activity-list" id="recent-activities-list">
                    <div class="activity-item activity-loading">
                        <div class="activity-icon">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="activity-details">
                            <p>جاري تحميل النشاطات الأخيرة...</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="page-content" id="inventory-page">
            <section>
                <h2 class="section-title"><i class="fas fa-tshirt"></i> إدارة المخزون</h2>
                <div class="quick-actions">
                    <a href="inventory.php" class="btn primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-tshirt"></i> <span>المخزون</span>
                    </a>
                </div>
            </section>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="copyright">
                <p>© 2025 <?php echo htmlspecialchars($_SESSION['store_name'] ?? 'Store Name'); ?>. جميع الحقوق محفوظة </p>
            </div>
            <div class="version">
                <p>الإصدار 0.2</p>
            </div>
        </div>
    </footer>

    <!-- Core Utilities (Load First) -->
    <script src="../assets/js/core/api-client.js"></script>
    <script src="../assets/js/core/formatters.js"></script>
    <script src="../assets/js/core/dom-utils.js"></script>
    <script src="../assets/js/core/validators.js"></script>
    
    <!-- Application Scripts -->
    <script src="../assets/js/index-javascript.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/global_search.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/recent-activities.js"></script>
</body>
</html>