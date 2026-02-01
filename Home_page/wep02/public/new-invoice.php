<?php
require_once __DIR__ . '/../includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة محل فساتين الزفاف | فاتورة جديدة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/index-style.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/new-invoice.css">
    <link rel="stylesheet" href="../assets/css/mobile-optimizations.css">
</head>
<body>
    <?php
    $activePage = 'sales'; // New invoice is part of sales section
    include __DIR__ . '/../includes/navbar.php';
    ?>

    <main class="main-content">
        <section class="invoice-header">
            <h1 class="section-title"><i class="fas fa-file-invoice"></i> فاتورة جديدة</h1>
            
            <div class="invoice-actions">
                <button class="btn primary" id="save-invoice-btn">
                    <i class="fas fa-save"></i> حفظ الفاتورة
                </button>
                <button class="btn secondary" id="cancel-invoice-btn">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button class="btn warning" id="print-invoice-btn">
                    <i class="fas fa-print"></i> طباعة
                </button>
            </div>
        </section>

        <form id="new-invoice-form" class="invoice-form">
            <div class="form-section">
                <h3><i class="fas fa-file-invoice"></i> بيانات الفاتورة</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice-number">رقم الفاتورة</label>
                        <input type="text" id="invoice-number" readonly>
                    </div>
                    <div class="form-group">
                        <label for="invoice-date">تاريخ إصدار الفاتورة</label>
                        <input type="date" id="invoice-date" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-exchange-alt"></i> تفاصيل العملية</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="operation-type">نوع العملية</label>
                        <select id="operation-type" required>
                            <option value="">اختر نوع العملية</option>
                            <option value="sale">بيع</option>
                            <option value="rent">إيجار</option>
                            <option value="design">تصميم</option>
                            <option value="design-sale">تصميم وبيع</option>
                            <option value="design-rent">تصميم وإيجار</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment-method">طريقة الدفع</label>
                        <select id="payment-method" required>
                            <option value="">اختر طريقة الدفع</option>
                            <option value="cash">نقداً</option>
                            <option value="card">بطاقة / شبكة</option>
                            <option value="transfer">تحويل بنكي</option>
                            <option value="mixed">أخرى / مختلطة</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-user"></i> بيانات العميل</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-name">اسم العميل</label>
                        <input type="text" id="customer-name" placeholder="اكتب اسم العميل الثلاثي" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone-number-1">رقم هاتف 1</label>
                        <input type="tel" id="phone-number-1" required>
                    </div>
                    <div class="form-group">
                        <label for="phone-number-2">رقم هاتف 2</label>
                        <input type="tel" id="phone-number-2">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-tshirt"></i> بيانات الفستان</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-name">اسم الفستان / الموديل</label>
                        <input type="text" id="dress-name" placeholder="أدخل اسم الفستان يدوياً" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-color">لون الفستان</label>
                        <input type="text" id="dress-color" required>
                    </div>
                    <div class="form-group">
                        <label for="dress-number">رقم الفستان (الرقم التسلسلي)</label>
                        <input type="text" id="dress-number" placeholder="مثال: D-0001">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-money-bill-wave"></i> بيانات السعر</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="total-price">السعر الإجمالي</label>
                        <input type="number" id="total-price" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="deposit-amount">قيمة العربون</label>
                        <input type="number" id="deposit-amount" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label for="remaining-balance">المبلغ المتبقي</label>
                        <input type="number" id="remaining-balance" readonly>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-ruler"></i> بيانات القياسات</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bust-measurement">مقاس محيط الصدر</label>
                        <input type="text" id="bust-measurement">
                    </div>
                    <div class="form-group">
                        <label for="waist-measurement">مقاس محيط الخصر</label>
                        <input type="text" id="waist-measurement">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dress-length">الطول الكلي</label>
                        <input type="text" id="dress-length">
                    </div>
                    <div class="form-group">
                        <label for="sleeve-length">طول الأكمام</label>
                        <input type="text" id="sleeve-length">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="shoulder-length">طول الأكتاف</label>
                        <input type="text" id="shoulder-length">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="other-measurements">قياسات أخرى</label>
                        <textarea id="other-measurements" rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
    <h3><i class="fas fa-calendar-alt"></i> التواريخ المهمة</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="wedding-date">تاريخ الزواج</label>
            <input type="date" id="wedding-date" onclick="this.showPicker()" style="cursor: pointer;">
        </div>
        <div class="form-group">
            <label for="collection-date">تاريخ استلام الفستان</label>
            <input type="date" id="collection-date" onclick="this.showPicker()" style="cursor: pointer;">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="return-date">تاريخ إرجاع الفستان</label>
            <input type="date" id="return-date" onclick="this.showPicker()" style="cursor: pointer;">
        </div>
    </div>
</div>
            
            <div class="form-section">
                <h3><i class="fas fa-gem"></i> بيانات الملحقات</h3>
                <div class="form-row">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="has-accessories">
                        <label for="has-accessories" style="font-weight: bold;">هل توجد ملحقات؟</label>
                    </div>
                </div>
                <div id="accessories-section" style="display: none; border-top: 1px solid #eee; padding-top: 10px;">
                    <p style="margin-bottom: 10px; color: #666;">اختر الملحقات:</p>
                    <div class="form-row" style="flex-wrap: wrap;">
                        <div class="form-group checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" id="acc-bouquet">
                            <label for="acc-bouquet">مسكة ورد</label>
                        </div>
                        <div class="form-group checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" id="acc-shoes">
                            <label for="acc-shoes">حذاء</label>
                        </div>
                        <div class="form-group checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" id="acc-tiara">
                            <label for="acc-tiara">تاج</label>
                        </div>
                        <div class="form-group checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" id="acc-set">
                            <label for="acc-set">طقم اكسسوار</label>
                        </div>
                        <div class="form-group checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" id="acc-abaya">
                            <label for="acc-abaya">عباية مغربية</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-sticky-note"></i> ملاحظات</h3>
                <div class="form-row">
                    <div class="form-group full-width">
                        <textarea id="invoice-notes" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </form>
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

    <script src="../assets/js/invoice-print.js"></script>
    <script src="../assets/js/new-invoice.js"></script>

</body>
</html>
