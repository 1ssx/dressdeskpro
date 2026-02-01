<?php
require_once __DIR__ . '/../includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | لوحة التحكم المالية</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/financial-dashboard.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
</head>
<body>
    <?php
    $activePage = 'reports';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <!-- Page Header -->
        <section class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-pie"></i> لوحة التحكم المالية</h1>
                    <p class="header-subtitle">عرض شامل لجميع الإيرادات والمصروفات والأرباح</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-export" id="export-excel-btn">
                        <i class="fas fa-file-excel"></i> تصدير Excel
                    </button>
                    <button class="btn btn-export" id="export-pdf-btn">
                        <i class="fas fa-file-pdf"></i> تصدير PDF
                    </button>
                </div>
            </div>
        </section>

        <!-- Quick Date Filters -->
        <section class="quick-filters">
            <div class="quick-filter-buttons">
                <button class="quick-filter active" data-range="today">اليوم</button>
                <button class="quick-filter" data-range="week">هذا الأسبوع</button>
                <button class="quick-filter" data-range="month">هذا الشهر</button>
                <button class="quick-filter" data-range="quarter">ربع سنوي</button>
                <button class="quick-filter" data-range="custom">نطاق مخصص</button>
            </div>
            <div class="custom-date-range" id="custom-date-range" style="display: none;">
                <div class="date-input-group">
                    <label>من</label>
                    <input type="date" id="filter-date-from" class="date-input">
                </div>
                <div class="date-input-group">
                    <label>إلى</label>
                    <input type="date" id="filter-date-to" class="date-input">
                </div>
                <button class="btn btn-primary" id="apply-custom-range">
                    <i class="fas fa-search"></i> عرض
                </button>
            </div>
        </section>

        <!-- KPI Summary Cards -->
        <section class="kpi-cards">
            <div class="kpi-card revenue">
                <div class="kpi-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">إجمالي الإيرادات</span>
                    <span class="kpi-value" id="kpi-revenue">0.00 ريال</span>
                    <span class="kpi-detail" id="kpi-revenue-detail">جاري التحميل...</span>
                </div>
                <div class="kpi-trend up">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
            
            <div class="kpi-card expenses">
                <div class="kpi-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">إجمالي المصروفات</span>
                    <span class="kpi-value" id="kpi-expenses">0.00 ريال</span>
                    <span class="kpi-detail" id="kpi-expenses-detail">جاري التحميل...</span>
                </div>
                <div class="kpi-trend down">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            
            <div class="kpi-card profit">
                <div class="kpi-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">صافي الربح</span>
                    <span class="kpi-value" id="kpi-profit">0.00 ريال</span>
                    <span class="kpi-detail" id="kpi-profit-detail">الإيرادات - المصروفات</span>
                </div>
                <div class="kpi-trend" id="kpi-profit-trend">
                    <i class="fas fa-equals"></i>
                </div>
            </div>
            
            <div class="kpi-card debts">
                <div class="kpi-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="kpi-content">
                    <span class="kpi-label">الديون المستحقة</span>
                    <span class="kpi-value" id="kpi-debts">0.00 ريال</span>
                    <span class="kpi-detail" id="kpi-debts-detail">المبالغ غير المسددة</span>
                </div>
                <div class="kpi-trend warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="charts-row">
                <!-- Revenue vs Expenses Bar Chart -->
                <div class="chart-card chart-large">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> الإيرادات مقابل المصروفات</h3>
                        <div class="chart-legend">
                            <span class="legend-item income"><span class="legend-dot"></span> الإيرادات</span>
                            <span class="legend-item expense"><span class="legend-dot"></span> المصروفات</span>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="revenue-expense-chart"></canvas>
                    </div>
                </div>
                
                <!-- Revenue by Payment Method Pie Chart -->
                <div class="chart-card chart-small">
                    <div class="chart-header">
                        <h3><i class="fas fa-credit-card"></i> طرق الدفع</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="payment-method-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="charts-row">
                <!-- Expense Categories Pie Chart -->
                <div class="chart-card chart-small">
                    <div class="chart-header">
                        <h3><i class="fas fa-tags"></i> فئات المصروفات</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="expense-category-chart"></canvas>
                    </div>
                </div>
                
                <!-- Daily Profit Trend Line Chart -->
                <div class="chart-card chart-large">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-area"></i> اتجاه الأرباح اليومية</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="profit-trend-chart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transaction Log Section -->
        <section class="transactions-section">
            <div class="section-header">
                <h2><i class="fas fa-exchange-alt"></i> سجل المعاملات المالية</h2>
                <div class="section-filters">
                    <select id="transaction-type-filter" class="filter-select">
                        <option value="">جميع المعاملات</option>
                        <option value="income">الإيرادات فقط</option>
                        <option value="expense">المصروفات فقط</option>
                    </select>
                </div>
            </div>
            
            <div class="transactions-table-container">
                <table class="transactions-table" id="transactions-table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>التاريخ</th>
                            <th>الوصف</th>
                            <th>العميل/الفئة</th>
                            <th>المبلغ</th>
                            <th>المرجع</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-table-body">
                        <tr><td colspan="6" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Outstanding Debts Section -->
        <section class="debts-section">
            <div class="section-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> الفواتير غير المسددة</h2>
                <a href="receivables_report.php" class="btn btn-link">عرض التقرير الكامل <i class="fas fa-arrow-left"></i></a>
            </div>
            
            <div class="debts-table-container">
                <table class="debts-table" id="debts-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>رقم الهاتف</th>
                            <th>إجمالي الفاتورة</th>
                            <th>المبلغ المتبقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="debts-table-body">
                        <tr><td colspan="6" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="copyright">
                <p>© 2024 نظام إدارة محل فساتين الزفاف. جميع الحقوق محفوظة.</p>
            </div>
            <div class="version">
                <p>الإصدار 3.0.0</p>
            </div>
        </div>
    </footer>

    <!-- Core Utilities -->
    <script src="../assets/js/core/api-client.js"></script>
    <script src="../assets/js/core/formatters.js"></script>
    <script src="../assets/js/core/dom-utils.js"></script>
    
    <!-- Financial Dashboard Script -->
    <script src="../assets/js/financial-dashboard.js"></script>
</body>
</html>
