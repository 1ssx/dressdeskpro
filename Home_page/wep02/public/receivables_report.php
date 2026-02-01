<?php
require_once __DIR__ . '/../includes/session_check.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الذمم والمستحقات | نظام إدارة محل الفساتين</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png">
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/sales.css">
    
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .summary-card .amount {
            font-size: 2rem;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .summary-card.total .amount { color: #e74c3c; }
        .summary-card.overdue .amount { color: #f39c12; }
        .summary-card.count .amount { color: #3498db; }
        
        .aging-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .aging-bars {
            margin-top: 20px;
        }
        
        .aging-bar {
            margin-bottom: 20px;
        }
        
        .aging-bar-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .aging-bar-progress {
            height: 30px;
            background: #ecf0f1;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .aging-bar-fill {
            height: 100%;
            display: flex;
            align-items: center;
            padding: 0 15px;
            color: white;
            font-weight: 600;
            transition: width 0.5s;
        }
        
        .aging-0-30 { background: #3498db; }
        .aging-31-60 { background: #f39c12; }
        .aging-61-90 { background: #e67e22; }
        .aging-90-plus { background: #e74c3c; }
        
        .btn-action {
            border: none;
            background: none;
            cursor: pointer;
            margin: 0 5px;
            font-size: 1.1em;
            transition: transform 0.2s;
        }
        .btn-action:hover { transform: scale(1.2); }
        .btn-action.details { color: #27ae60; }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .customer-summary {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .customer-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .customer-item:last-child {
            border-bottom: none;
        }
        
        .customer-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php
    $activePage = 'reports';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
            
            <!-- Header -->
            <h1 class="section-title">
                <i class="fas fa-file-invoice-dollar"></i> تقرير الذمم والمستحقات
            </h1>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card total">
                    <h3>إجمالي المستحقات</h3>
                    <p class="amount" id="total-receivables">0 ريال</p>
                </div>
                <div class="summary-card count">
                    <h3>عدد الفواتير المستحقة</h3>
                    <p class="amount" id="total-invoices">0</p>
                </div>
                <div class="summary-card overdue">
                    <h3>متوسط العمر (أيام)</h3>
                    <p class="amount" id="avg-age">0</p>
                </div>
            </div>
            
            <!-- Aging Analysis -->
            <div class="aging-section">
                <h2 style="margin-top: 0;">
                    <i class="fas fa-chart-pie"></i> تحليل أعمار الديون
                </h2>
                <div class="aging-bars" id="aging-bars">
                    <p>جاري التحميل...</p>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="customer-summary">
                <h2 style="margin-top: 0;">
                    <i class="fas fa-users"></i> أعلى 10 عملاء من حيث المستحقات
                </h2>
                <div id="top-customers">
                    <p>جاري التحميل...</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <h3 style="margin-top: 0;">
                    <i class="fas fa-filter"></i> فلاتر البحث
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">الحد الأدنى للمبلغ</label>
                        <input type="number" id="filter-min-amount" placeholder="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">بحث بالعميل</label>
                        <input type="text" id="filter-customer" placeholder="اسم العميل..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>
                <button onclick="applyFilters()" style="margin-top: 15px; padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-search"></i> بحث
                </button>
            </div>
            
            <!-- Invoices Table -->
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;">
                    <i class="fas fa-list"></i> الفواتير غير المسددة
                </h2>
                <table class="invoices-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>التاريخ</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>العمر (أيام)</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="receivables-table-body">
                        <tr>
                            <td colspan="8" style="text-align:center">جاري التحميل...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>
    </main>

    <script>
        const API_URL = 'api/receivables.php';
        
        document.addEventListener('DOMContentLoaded', function() {
            loadReceivables();
            loadAgingReport();
            loadTopCustomers();
        });
        
        // Load receivables list
        function loadReceivables() {
            let url = `${API_URL}?action=list`;
            
            const minAmount = document.getElementById('filter-min-amount').value;
            if (minAmount) url += `&min_amount=${minAmount}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderReceivablesTable(data.data.invoices || []);
                        updateSummary(data.data);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showError('فشل تحميل البيانات');
                });
        }
        
        // Load aging report
        function loadAgingReport() {
            fetch(`${API_URL}?action=aging`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderAgingBars(data.data.aging_buckets || [], data.data.total_amount || 0);
                    }
                })
                .catch(err => console.error('Error:', err));
        }
        
        // Load top customers
        function loadTopCustomers() {
            fetch(`${API_URL}?action=by_customer&min_amount=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderTopCustomers((data.data.customers || []).slice(0, 10));
                    }
                })
                .catch(err => console.error('Error:', err));
        }
        
        // Render receivables table
        function renderReceivablesTable(invoices) {
            const tbody = document.getElementById('receivables-table-body');
            
            // Filter by customer if needed
            const customerFilter = document.getElementById('filter-customer').value.toLowerCase();
            if (customerFilter) {
                invoices = invoices.filter(inv => 
                    (inv.customer_name || '').toLowerCase().includes(customerFilter)
                );
            }
            
            if (!invoices || invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">لا توجد مستحقات</td></tr>';
                return;
            }
            
            let html = '';
            invoices.forEach(invoice => {
                const age = invoice.days_overdue || 0;
                let ageClass = '';
                if (age <= 30) ageClass = 'color: #3498db;';
                else if (age <= 60) ageClass = 'color: #f39c12;';
                else if (age <= 90) ageClass = 'color: #e67e22;';
                else ageClass = 'color: #e74c3c;';
                
                html += `
                    <tr>
                        <td>${invoice.invoice_number || 'N/A'}</td>
                        <td>${invoice.customer_name || 'غير محدد'}</td>
                        <td>${formatDate(invoice.invoice_date)}</td>
                        <td>${formatCurrency(invoice.total_price)}</td>
                        <td>${formatCurrency(invoice.total_paid || 0)}</td>
                        <td style="font-weight: bold; color: #e74c3c;">${formatCurrency(invoice.remaining_balance)}</td>
                        <td style="${ageClass} font-weight: bold;">${age}</td>
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
        
        // Render aging bars
        function renderAgingBars(buckets, totalAmount) {
            const container = document.getElementById('aging-bars');
            
            if (!buckets || buckets.length === 0) {
                container.innerHTML = '<p>لا توجد بيانات</p>';
                return;
            }
            
            let html = '';
            buckets.forEach(bucket => {
                const percentage = bucket.percentage || 0;
                const amount = bucket.total_amount || 0;
                const count = bucket.invoice_count || 0;
                
                let className = 'aging-0-30';
                if (bucket.aging_bucket === '31-60') className = 'aging-31-60';
                else if (bucket.aging_bucket === '61-90') className = 'aging-61-90';
                else if (bucket.aging_bucket === '90+') className = 'aging-90-plus';
                
                html += `
                    <div class="aging-bar">
                        <div class="aging-bar-header">
                            <strong>${bucket.aging_bucket} يوم</strong>
                            <span>${formatCurrency(amount)} (${count} فاتورة)</span>
                        </div>
                        <div class="aging-bar-progress">
                            <div class="aging-bar-fill ${className}" style="width: ${percentage}%">
                                ${percentage.toFixed(1)}%
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Render top customers
        function renderTopCustomers(customers) {
            const container = document.getElementById('top-customers');
            
            if (!customers || customers.length === 0) {
                container.innerHTML = '<p>لا توجد بيانات</p>';
                return;
            }
            
            let html = '';
            customers.forEach((customer, index) => {
                html += `
                    <div class="customer-item">
                        <div>
                            <strong style="color: #2c3e50;">${index + 1}. ${customer.customer_name}</strong>
                            <p style="margin: 5px 0 0 0; color: #7f8c8d; font-size: 0.9rem;">
                                ${customer.invoice_count} فاتورة | ${customer.phone_1 || '-'}
                            </p>
                        </div>
                        <div style="text-align: left;">
                            <strong style="color: #e74c3c; font-size: 1.2rem;">${formatCurrency(customer.total_remaining)}</strong>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Update summary
        function updateSummary(data) {
            document.getElementById('total-receivables').textContent = formatCurrency(data.total_remaining || 0);
            document.getElementById('total-invoices').textContent = data.count || 0;
            
            // Calculate average age
            const invoices = data.invoices || [];
            if (invoices.length > 0) {
                const totalAge = invoices.reduce((sum, inv) => sum + (inv.days_overdue || 0), 0);
                const avgAge = Math.round(totalAge / invoices.length);
                document.getElementById('avg-age').textContent = avgAge;
            }
        }
        
        // Apply filters
        function applyFilters() {
            loadReceivables();
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
        
        function showError(message) {
            const tbody = document.getElementById('receivables-table-body');
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:red">${message}</td></tr>`;
        }
    </script>
</body>
</html>
