<?php
require_once __DIR__ . '/../includes/session_check.php';

// Get invoice ID from URL
$invoiceId = $_GET['invoice_id'] ?? $_GET['id'] ?? null;

if (!$invoiceId) {
    header('Location: sales.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الفاتورة | نظام إدارة محل الفساتين</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-transparent.png">
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/invoice-details.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
    
    <style>
        /* Page-specific styles for invoice details */
        .invoice-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .invoice-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .invoice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        

        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <?php
    $activePage = 'sales';
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <div class="invoice-container">
            <!-- Alert Messages -->
            <div id="alert-container"></div>
            
            <!-- Loading State -->
            <div id="loading-state" style="text-align: center; padding: 50px;">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري تحميل تفاصيل الفاتورة...</p>
            </div>
            
            <!-- Invoice Content (Hidden initially) -->
            <div id="invoice-content" style="display: none;">
                
                <!-- Back Button -->
                <div style="margin-bottom: 20px;">
                    <a href="sales.php" class="btn-action btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة للفواتير
                    </a>
                </div>
                
                <!-- Invoice Header -->
                <div class="invoice-header">
                    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap;">
                        <div>
                            <h1 style="margin: 0 0 10px 0;">
                                <i class="fas fa-file-invoice"></i>
                                فاتورة رقم: <span id="invoice-number">-</span>
                            </h1>
                            <p style="margin: 5px 0; color: #7f8c8d;">
                                <i class="fas fa-calendar"></i>
                                التاريخ: <span id="invoice-date">-</span>
                            </p>
                            <p style="margin: 5px 0;">
                                <i class="fas fa-user"></i>
                                العميل: <span id="customer-name" style="font-weight: 600;">-</span>
                            </p>
                        </div>
                        <div style="text-align: left;">
                            <p style="margin: 5px 0;">حالة الفاتورة:</p>
                            <span id="invoice-status-badge" class="status-badge">-</span>
                            <p style="margin: 10px 0 5px 0;">حالة الدفع:</p>
                            <span id="payment-status-badge" class="status-badge">-</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="invoice-grid">
                    <!-- Invoice Details -->
                    <div class="section-card" style="grid-column: 1 / -1;">
                        <h2 class="section-title"><i class="fas fa-info-circle"></i> تفاصيل الفاتورة</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>نوع العملية:</strong>
                                <p id="operation-type">-</p>
                            </div>
                            <div>
                                <strong>طريقة الدفع:</strong>
                                <p id="payment-method">-</p>
                            </div>
                            <div>
                                <strong>تاريخ الزفاف:</strong>
                                <p id="wedding-date">-</p>
                            </div>
                            <div>
                                <strong>تاريخ الاستلام:</strong>
                                <p id="collection-date">-</p>
                            </div>
                            <div>
                                <strong>تاريخ الإرجاع:</strong>
                                <p id="return-date">-</p>
                            </div>
                            <div>
                                <strong>تم التسليم:</strong>
                                <p id="delivered-at">-</p>
                            </div>
                            <div>
                                <strong>تم الإرجاع:</strong>
                                <p id="returned-at">-</p>
                            </div>
                            <div>
                                <strong>حالة الإرجاع:</strong>
                                <p id="return-condition">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items & Payments Grid -->
                <div class="invoice-grid">
                    <!-- Invoice Items -->
                    <div class="section-card">
                        <h2 class="section-title"><i class="fas fa-shopping-bag"></i> عناصر الفاتورة</h2>
                        <div class="table-responsive">
                            <table id="items-table">
                                <thead>
                                    <tr>
                                        <th>الصنف</th>
                                        <th>الكود</th>
                                        <th>الكمية</th>
                                        <th>السعر</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="4">جاري التحميل...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                            <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                                <strong>الإجمالي:</strong>
                                <span id="total-price" style="font-size: 1.2rem; font-weight: bold; color: #2c3e50;">0 ريال</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payments Section -->
                    <div class="section-card">
                        <h2 class="section-title">
                            <i class="fas fa-money-bill-wave"></i> المدفوعات
                            <button onclick="openAddPaymentModal()" class="btn-action btn-success" style="float: left; padding: 8px 15px; font-size: 0.9rem;">
                                <i class="fas fa-plus"></i> إضافة دفعة
                            </button>
                        </h2>
                        
                        <div class="table-responsive">
                            <table id="payments-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>المبلغ</th>
                                        <th>الطريقة</th>
                                        <th>النوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="4">لا توجد مدفوعات</td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                            <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                                <span>المدفوع:</span>
                                <strong id="total-paid" style="color: #27ae60;">0 ريال</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                                <span>المتبقي:</span>
                                <strong id="remaining-balance" style="color: #e74c3c; font-size: 1.2rem;">0 ريال</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline / History -->
                <div class="section-card">
                    <h2 class="section-title"><i class="fas fa-history"></i> سجل التغييرات</h2>
                    <div id="timeline-container" class="timeline">
                        <p>جاري تحميل السجل...</p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="section-card">
                    <h2 class="section-title"><i class="fas fa-tasks"></i> إجراءات الفاتورة</h2>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <button id="btn-deliver" onclick="handleDeliver()" class="btn-action btn-primary">
                            <i class="fas fa-truck"></i> تأكيد التسليم
                        </button>
                        <button id="btn-return" onclick="handleReturn()" class="btn-action btn-warning">
                            <i class="fas fa-undo"></i> تأكيد الإرجاع
                        </button>
                        <button id="btn-close" onclick="handleClose()" class="btn-action btn-success">
                            <i class="fas fa-check-circle"></i> إقفال الفاتورة
                        </button>
                        <button id="btn-cancel" onclick="handleCancel()" class="btn-action btn-danger">
                            <i class="fas fa-times-circle"></i> إلغاء الفاتورة
                        </button>
                        <button id="btn-print" onclick="PrintInvoice.printById(invoiceId)" class="btn-action btn-secondary">
                            <i class="fas fa-print"></i> طباعة
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </main>

    <!-- Add Payment Modal -->
    <div id="add-payment-modal" class="modal">
        <div class="modal-content">
            <h2 style="margin-top: 0;"><i class="fas fa-money-bill"></i> إضافة دفعة جديدة</h2>
            <form id="add-payment-form">
                <div class="form-group">
                    <label for="payment-amount">المبلغ *</label>
                    <input type="number" id="payment-amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="payment-method-input">طريقة الدفع *</label>
                    <select id="payment-method-input" required>
                        <option value="cash">نقدي</option>
                        <option value="card">بطاقة</option>
                        <option value="transfer">تحويل</option>
                        <option value="mixed">مختلط</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment-type">نوع الدفعة *</label>
                    <select id="payment-type">
                        <option value="payment">دفعة</option>
                        <option value="refund">مرتجع</option>
                        <option value="penalty">غرامة</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment-notes">ملاحظات</label>
                    <textarea id="payment-notes" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn-action btn-success">
                        <i class="fas fa-save"></i> حفظ
                    </button>
                    <button type="button" onclick="closeAddPaymentModal()" class="btn-action btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Return Modal -->
    <div id="return-modal" class="modal">
        <div class="modal-content">
            <h2 style="margin-top: 0;"><i class="fas fa-undo"></i> تأكيد إرجاع الفستان</h2>
            <form id="return-form">
                <div class="form-group">
                    <label for="return-condition">حالة الفستان *</label>
                    <select id="return-condition" name="return_condition" class="form-control" required>
                        <option value="">-- اختر الحالة --</option>
                        <option value="excellent">ممتاز</option>
                        <option value="good">جيد</option>
                        <option value="needs_cleaning">يحتاج تنظيف</option>
                        <option value="damaged">متضرر</option>
                        <option value="missing_items">ناقص ملحقات</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="return-notes">ملاحظات</label>
                    <textarea id="return-notes" name="return_notes" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" data-action="confirm-return" class="btn-action btn-warning">
                        <i class="fas fa-check"></i> تأكيد الإرجاع
                    </button>
                    <button type="button" onclick="closeReturnModal()" class="btn-action btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const invoiceId = <?php echo json_encode($invoiceId); ?>;
        let invoiceData = null;
    </script>
    <script src="../assets/js/invoice-print.js"></script>
    <script src="../assets/js/invoice-details.js"></script>
    <script src="../assets/js/invoice-lifecycle.js"></script>
    <script src="../assets/js/payments-manager.js"></script>
</body>
</html>
