
<?php
require_once __DIR__ . '/../includes/session_check.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | المبيعات والفواتير</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="apple-touch-icon" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">

    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/sales.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">

    <style>
        /* Page-specific overrides for sales.php */
        .page-sales .expenses-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php
    $activePage = 'sales';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <!-- كروت الإحصائيات -->
        <section class="sales-header">
            <h1 class="section-title">
                <i class="fas fa-cash-register"></i> المبيعات والفواتير
            </h1>
            
            <div class="stats-controls" style="margin-bottom: 15px; display: flex; gap: 10px;">
                <button class="btn small primary active" id="stats-daily-btn" onclick="SalesDashboard.setStatsPeriod('daily')">يومي</button>
                <button class="btn small secondary" id="stats-weekly-btn" onclick="SalesDashboard.setStatsPeriod('weekly')">أسبوعي</button>
            </div>
            
            <div class="sales-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>إجمالي المبيعات</h3>
                        <p class="stat-value" id="stat-today-total">جاري التحميل...</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-content">
                        <h3>عدد الفواتير</h3>
                        <p class="stat-value" id="stat-today-count">جاري التحميل...</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-content">
                        <h3>متوسط قيمة الفاتورة</h3>
                        <p class="stat-value" id="stat-today-avg">جاري التحميل...</p>
                    </div>
                </div>
                
                
                <div class="stat-card">
    <div class="stat-icon">
        <i class="fas fa-chart-line"></i>
    </div>
    <div class="stat-content">
        <h3>إجمالي الإيرادات</h3>
        <p class="stat-value" id="stat-revenue">جاري التحميل...</p>
    </div>
</div>

            </div>
        </section>

        <!-- أزرار الإجراءات -->
        <section class="sales-actions">
            <a href="new-invoice.php" class="btn primary" id="new-invoice-btn">
                <i class="fas fa-plus"></i> فاتورة جديدة
            </a>
            <!-- التعديل الأول: تغيير ID الزر واستخدام أيقونة الكتاب (بدلاً من quick-sale-btn) -->
            <button class="btn success" id="daily-ledger-btn">
                <i class="fas fa-book"></i> الدفتر اليومي
            </button>
            <button class="btn warning" id="print-expenses-today-btn">
                <i class="fas fa-print"></i> طباعة مصروفات اليوم
            </button>
        </section>

        <!-- التبويبات -->
        <section class="sales-tabs">
            <div class="tabs">
                <div class="tab active" data-tab="invoices-content">الفواتير</div>
                <div class="tab" data-tab="expenses-content">المصروفات</div>
                <!-- Returns Tab Removed -->
            </div>

            <!-- تبويب الفواتير -->
            <div class="tab-content active" id="invoices-content">
                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="invoice-search" placeholder="بحث عن فاتورة...">
                    </div>
                    
                    <!-- فلاتر الحالة الجديدة -->
                    <div class="status-filters">
                        <button class="filter-btn active" data-status="all">
                            <i class="fas fa-list"></i> الكل
                        </button>
                        <button class="filter-btn" data-status="reserved">
                            <i class="fas fa-bookmark"></i> محجوز
                        </button>
                        <button class="filter-btn" data-status="out_with_customer">
                            <i class="fas fa-truck"></i> مع العميل
                        </button>
                        <button class="filter-btn" data-status="returned">
                            <i class="fas fa-undo"></i> مرتجع
                        </button>
                        <button class="filter-btn" data-status="closed">
                            <i class="fas fa-archive"></i> الأرشيف (مقفلة)
                        </button>
                        <button class="filter-btn" data-status="canceled">
                            <i class="fas fa-times-circle"></i> ملغاة
                        </button>
                    </div>
                </div>
                
                <div class="invoices-table-container">
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>الإجمالي</th>
                                <th>المدفوع</th>
                                <th>المتبقي</th>
                                <th>حالة الفاتورة</th>
                                <th>حالة الدفع</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="invoices-table-body">
                            <tr>
                                <td colspan="9" style="text-align:center">
                                    جاري الاتصال بقاعدة البيانات...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- تبويب المصروفات -->
            <div class="tab-content" id="expenses-content">
                <div class="sales-actions">
                    <button id="add-expense-btn" class="btn primary">
                        <i class="fas fa-plus"></i> إضافة مصروف
                    </button>
                    <button id="print-expenses-btn" class="btn secondary">
                        <i class="fas fa-print"></i> طباعة مصروفات اليوم
                    </button>

                </div>

                <div class="date-filter" style="margin: 20px 0;">
                    <label>تاريخ المصروفات:</label>
                    <input type="date" id="expense-date-filter" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                </div>

                <!-- ملخص الدخل والمصروفات -->
                <div class="expenses-summary">
                    <div class="summary-card income">
                        <h4>إجمالي الإيرادات</h4>
                        <p class="value" id="total-income-today">0 ريال</p>
                    </div>
                    <div class="summary-card expense">
                        <h4>إجمالي المصروفات</h4>
                        <p class="value" id="total-expenses-today">0 ريال</p>
                    </div>
                    <div class="summary-card net">
                        <h4>صافي الدخل</h4>
                        <p class="value" id="net-income-today">0 ريال</p>
                    </div>
                </div>

                <!-- جدول المصروفات -->
                <div class="invoices-table-container">
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفئة</th>
                                <th>المبلغ</th>
                                <th>الوصف</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="expenses-table-body">
                            <tr><td colspan="5" style="text-align:center">جاري التحميل...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Returns Tab Content Removed -->
        </section>
    </main>

    <!-- مودال إضافة مصروف -->
    <div id="expense-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="ExpensesManager.closeModal()">&times;</span>
            <h2>إضافة مصروف جديد</h2>
            <form id="expense-form">
                <input type="hidden" id="expense-id">
                <div class="form-group">
                    <label>التاريخ</label>
                    <input type="date" id="expense-date" required>
                </div>
                <div class="form-group">
                    <label>المبلغ</label>
                    <input type="number" id="expense-amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>الفئة</label>
                    <select id="expense-category" required></select>
                </div>
                <div class="form-group">
                    <label>الوصف (اختياري)</label>
                    <textarea id="expense-description"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn primary">حفظ</button>
                    <button type="button" class="btn secondary" onclick="ExpensesManager.closeModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <!-- تحميل السكريبتات -->
    <script src="../assets/js/invoice-print.js"></script>
    <script src="../assets/js/invoice-image-generator.js"></script>
     <script src="../assets/js/sales.js"></script>
     <script src="../assets/js/expenses.js"></script>
     <script src="../assets/js/print-expense.js"></script>
     <!-- التعديل الثاني: إضافة سكريبت الدفتر اليومي الجديد -->
     <script src="../assets/js/daily-report.js"></script>


    
    <script>
    // Tab switching
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                const targetId = this.getAttribute('data-tab');
                document.getElementById(targetId).classList.add('active');
            });
        });
    });
    </script>

</body>
</html>