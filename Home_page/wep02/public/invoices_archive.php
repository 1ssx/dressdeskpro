<?php
require_once __DIR__ . '/../includes/session_check.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أرشيف الفواتير | نظام إدارة محل الفساتين</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png">
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/sales.css">
    
    <style>
        /* Page-specific styles for archive page */
        .page-archive .archive-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .page-archive .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .page-archive .stat-card h4 {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .page-archive .stat-card .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <?php
    $activePage = 'sales';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
            
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 class="section-title">
                    <i class="fas fa-archive"></i> أرشيف الفواتير
                </h1>
                <a href="sales.php" class="btn-reset">
                    <i class="fas fa-arrow-right"></i> العودة للمبيعات
                </a>
            </div>
            
            <!-- Statistics -->
            <div class="archive-stats">
                <div class="stat-card">
                    <h4>إجمالي الفواتير المؤرشفة</h4>
                    <p class="value" id="total-archived">0</p>
                </div>
                <div class="stat-card">
                    <h4>الفواتير المقفلة</h4>
                    <p class="value" id="total-closed" style="color: #27ae60;">0</p>
                </div>
                <div class="stat-card">
                    <h4>الفواتير الملغاة</h4>
                    <p class="value" id="total-canceled" style="color: #95a5a6;">0</p>
                </div>
                <div class="stat-card">
                    <h4>الفواتير المرتجعة</h4>
                    <p class="value" id="total-returned" style="color: #9b59b6;">0</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <h3 style="margin-top: 0;">
                    <i class="fas fa-filter"></i> فلاتر البحث
                </h3>
                <div class="filter-row">
                    <div class="filter-group">
                        <label>من تاريخ</label>
                        <input type="date" id="filter-start-date">
                    </div>
                    <div class="filter-group">
                        <label>إلى تاريخ</label>
                        <input type="date" id="filter-end-date">
                    </div>
                    <div class="filter-group">
                        <label>حالة الفاتورة</label>
                        <select id="filter-status">
                            <option value="">الكل</option>
                            <option value="closed">مقفلة</option>
                            <option value="canceled">ملغاة</option>
                            <option value="returned">مرتجعة</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>بحث بالعميل</label>
                        <input type="text" id="filter-customer" placeholder="اسم العميل...">
                    </div>
                </div>
                <div class="filter-actions">
                    <button onclick="applyFilters()" class="btn-filter">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <button onclick="resetFilters()" class="btn-reset">
                        <i class="fas fa-redo"></i> إعادة تعيين
                    </button>
                </div>
            </div>
            
            <!-- Table -->
            <div class="invoices-table-container" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
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
                    <tbody id="archive-table-body">
                        <tr>
                            <td colspan="9" style="text-align:center">
                                جاري التحميل...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>
    </main>

    <script>
        const API_URL = 'api/invoices.php';
        
        // Load archive on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadArchive();
        });
        
        // Load archived invoices
        function loadArchive() {
            let url = `${API_URL}?action=archive_list`;
            
            const startDate = document.getElementById('filter-start-date').value;
            const endDate = document.getElementById('filter-end-date').value;
            const status = document.getElementById('filter-status').value;
            
            if (startDate) url += `&start_date=${startDate}`;
            if (endDate) url += `&end_date=${endDate}`;
            if (status) url += `&status=${status}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderArchiveTable(data.data.invoices || []);
                        updateStats(data.data.invoices || []);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showError('فشل تحميل البيانات');
                });
        }
        
        // Render table
        function renderArchiveTable(invoices) {
            const tbody = document.getElementById('archive-table-body');
            
            if (!invoices || invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center">لا توجد فواتير مؤرشفة</td></tr>';
                return;
            }
            
            // Filter by customer if needed
            const customerFilter = document.getElementById('filter-customer').value.toLowerCase();
            if (customerFilter) {
                invoices = invoices.filter(inv => 
                    (inv.customer_name || '').toLowerCase().includes(customerFilter)
                );
            }
            
            let html = '';
            invoices.forEach(invoice => {
                const invoiceStatus = invoice.invoice_status || 'closed';
                const paymentStatus = invoice.payment_status || 'unpaid';
                
                html += `
                    <tr>
                        <td>${invoice.invoice_number || 'N/A'}</td>
                        <td>${formatDate(invoice.invoice_date)}</td>
                        <td>${invoice.customer_name || 'غير محدد'}</td>
                        <td>${formatCurrency(invoice.total_price)}</td>
                        <td>${formatCurrency(invoice.total_paid || 0)}</td>
                        <td>${formatCurrency(invoice.remaining_balance || 0)}</td>
                        <td>
                            <span class="status-badge status-${invoiceStatus}">
                                ${getInvoiceStatusText(invoiceStatus)}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge payment-${paymentStatus}">
                                ${getPaymentStatusText(paymentStatus)}
                            </span>
                        </td>
                        <td>
                            <button class="btn-action details" onclick="viewDetails(${invoice.id})" title="تفاصيل">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // Update statistics
        function updateStats(invoices) {
            const total = invoices.length;
            const closed = invoices.filter(i => i.invoice_status === 'closed').length;
            const canceled = invoices.filter(i => i.invoice_status === 'canceled').length;
            const returned = invoices.filter(i => i.invoice_status === 'returned').length;
            
            document.getElementById('total-archived').textContent = total;
            document.getElementById('total-closed').textContent = closed;
            document.getElementById('total-canceled').textContent = canceled;
            document.getElementById('total-returned').textContent = returned;
        }
        
        // Apply filters
        function applyFilters() {
            loadArchive();
        }
        
        // Reset filters
        function resetFilters() {
            document.getElementById('filter-start-date').value = '';
            document.getElementById('filter-end-date').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-customer').value = '';
            loadArchive();
        }
        
        // View details
        function viewDetails(id) {
            window.location.href = `invoice_details.php?invoice_id=${id}`;
        }
        
        // Helper functions
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB');
        }
        
        function formatCurrency(amount) {
            return parseFloat(amount || 0).toFixed(2) + ' ريال';
        }
        
        function getInvoiceStatusText(status) {
            const map = {
                'draft': 'مسودة',
                'reserved': 'محجوز',
                'out_with_customer': 'مع العميل',
                'returned': 'مرتجع',
                'closed': 'مقفلة',
                'canceled': 'ملغاة'
            };
            return map[status] || status;
        }
        
        function getPaymentStatusText(status) {
            const map = {
                'paid': 'مدفوع',
                'partial': 'جزئي',
                'unpaid': 'غير مدفوع'
            };
            return map[status] || status;
        }
        
        function showError(message) {
            const tbody = document.getElementById('archive-table-body');
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:red">${message}</td></tr>`;
        }
    </script>
</body>
</html>
