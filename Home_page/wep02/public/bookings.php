<?php
require_once __DIR__ . '/../includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | إدارة الحجوزات</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo-transparent.png?v=<?php echo time(); ?>">
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/bookings.css">
</head>
<body>
    <?php
    $activePage = 'bookings';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <section class="bookings-header">
            <h1 class="section-title"><i class="fas fa-calendar-check"></i> إدارة الحجوزات</h1>
            
            <!-- Stats Bar -->
            <div class="bookings-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3>حجوزات اليوم</h3>
                        <p class="stat-value" id="today-count">...</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <h3>حجوزات الأسبوع</h3>
                        <p class="stat-value" id="week-count">...</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>الملغيات</h3>
                        <p class="stat-value" id="cancelled-count">...</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>المتأخرة</h3>
                        <p class="stat-value" id="late-count">...</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Action Bar -->
        <section class="bookings-actions">
            <button class="btn primary" id="add-booking-btn">
                <i class="fas fa-plus"></i> إضافة حجز جديد
            </button>
            
            <div class="filters">
                <input type="date" id="filter-date-from" class="filter-input" placeholder="من تاريخ">
                <input type="date" id="filter-date-to" class="filter-input" placeholder="إلى تاريخ">
                <select id="filter-status" class="filter-select">
                    <option value="">جميع الحالات</option>
                    <option value="pending">قيد الانتظار</option>
                    <option value="confirmed">مؤكد</option>
                    <option value="completed">مكتمل</option>
                    <option value="cancelled">ملغي</option>
                    <option value="late">متأخر</option>
                </select>
                <select id="filter-booking-type" class="filter-select">
                    <option value="">جميع الأنواع</option>
                    <option value="تجربة">تجربة</option>
                    <option value="قياسات">قياسات</option>
                    <option value="استلام">استلام</option>
                    <option value="إرجاع">إرجاع</option>
                    <option value="تصميم">تصميم</option>
                    <option value="تصوير">تصوير</option>
                    <option value="زفة">زفة</option>
                </select>
                <button class="btn secondary" id="apply-filters-btn">
                    <i class="fas fa-filter"></i> تطبيق الفلاتر
                </button>
                <button class="btn secondary" id="clear-filters-btn">
                    <i class="fas fa-times"></i> مسح
                </button>
            </div>
            
            <div class="view-toggle">
                <button class="btn secondary" id="toggle-view-btn">
                    <i class="fas fa-calendar-alt"></i> عرض التقويم
                </button>
            </div>
        </section>

        <!-- Table View -->
        <section class="bookings-table-section" id="table-view">
            <div class="table-container">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>رقم الحجز</th>
                            <th>العميل</th>
                            <th>الجوال</th>
                            <th>نوع الحجز</th>
                            <th>التاريخ والوقت</th>
                            <th>الحالة</th>
                            <th>الفستان المرتبط</th>
                            <th>ملاحظات</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="bookings-table-body">
                        <tr>
                            <td colspan="9" class="loading">جاري التحميل...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Calendar View -->
        <section class="bookings-calendar-section" id="calendar-view" style="display: none;">
            <div id="calendar"></div>
        </section>
    </main>

    <!-- Booking Modal (Add/Edit) -->
    <div id="booking-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">إضافة حجز جديد</h2>
                <button class="modal-close" id="close-modal-btn">&times;</button>
            </div>
            <form id="booking-form">
                <input type="hidden" id="booking-id" name="id">
                
                <div class="form-group">
                    <label for="booking-customer">العميل <span class="required">*</span></label>
                    <select id="booking-customer" name="customer_id" required>
                        <option value="">اختر العميل</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking-type">نوع الحجز <span class="required">*</span></label>
                    <select id="booking-type" name="booking_type" required>
                        <option value="">اختر نوع الحجز</option>
                        <option value="تجربة">تجربة</option>
                        <option value="قياسات">قياسات</option>
                        <option value="استلام">استلام</option>
                        <option value="إرجاع">إرجاع</option>
                        <option value="تصميم">تصميم</option>
                        <option value="تصوير">تصوير</option>
                        <option value="زفة">زفة</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking-date">التاريخ والوقت <span class="required">*</span></label>
                    <input type="datetime-local" id="booking-date" name="booking_date" required>
                </div>
                
                <div class="form-group">
                    <label for="booking-status">الحالة</label>
                    <select id="booking-status" name="status">
                        <option value="pending">قيد الانتظار</option>
                        <option value="confirmed">مؤكد</option>
                        <option value="completed">مكتمل</option>
                        <option value="cancelled">ملغي</option>
                        <option value="late">متأخر</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking-invoice">الفستان المرتبط (اختياري)</label>
                    <select id="booking-invoice" name="invoice_id">
                        <option value="">لا يوجد</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking-notes">ملاحظات</label>
                    <textarea id="booking-notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn primary" id="save-booking-btn">
                        <i class="fas fa-save"></i> حفظ
                    </button>
                    <button type="button" class="btn secondary" id="cancel-booking-btn">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="copyright">
                <p>© 2025 <?php echo htmlspecialchars($_SESSION['store_name'] ?? 'نظام إدارة محل فساتين الزفاف'); ?>. جميع الحقوق محفوظة.</p>
            </div>
            <div class="version">
                <p>الإصدار 2.1.0</p>
            </div>
        </div>
    </footer>

    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/locales/ar.js"></script>
    
    <!-- Application Scripts -->
    <script src="../assets/js/bookings.js"></script>
</body>
</html>
