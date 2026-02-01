# خطة التنفيذ الشاملة لإكمال ميزات النظام

## نظرة عامة
إضافة الميزات الأساسية الناقصة لنظام إدارة محل فساتين الزفاف لجعله جاهزاً للاستخدام الفعلي بنسبة 100%.

---

## المراحل الرئيسية

### 📋 المرحلة 1: تحديث قاعدة البيانات (Database Schema Updates)
**الأولوية: عالية جداً**

#### 1.1 إضافة حقول دورة حياة الفاتورة
- إضافة `invoice_status` إلى جدول `invoices`
  - القيم: draft, reserved, out_with_customer, returned, closed, canceled
- إضافة `deleted_at` للـ Soft Delete

#### 1.2 إضافة حقول التسليم والإرجاع
- `delivered_at` (DATETIME)
- `delivered_by` (INT FK -> users.id)
- `returned_at` (DATETIME) 
- `returned_by` (INT FK -> users.id)
- `return_condition` (ENUM: excellent, good, needs_cleaning, damaged, missing_items)
- `return_notes` (TEXT)

#### 1.3 إنشاء جدول المدفوعات
```sql
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('cash', 'card', 'transfer', 'mixed') NOT NULL,
    type ENUM('payment', 'refund', 'penalty') NOT NULL DEFAULT 'payment',
    notes TEXT,
    created_by INT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_invoice (invoice_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.4 إنشاء جدول سجل تغييرات الفاتورة
```sql
CREATE TABLE invoice_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    status_from VARCHAR(50),
    status_to VARCHAR(50) NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    changed_by INT UNSIGNED,
    notes TEXT,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_history_invoice (invoice_id),
    INDEX idx_history_date (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.5 إنشاء جدول السجلات
```sql
CREATE TABLE store_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action_type VARCHAR(100) NOT NULL,
    related_type VARCHAR(50),
    related_id INT UNSIGNED,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_user (user_id),
    INDEX idx_log_action (action_type),
    INDEX idx_log_date (created_at),
    INDEX idx_log_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.6 إضافة حقل is_locked إلى products (إن لم يكن موجوداً)
```sql
ALTER TABLE products 
ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER status;
```

---

### 📋 المرحلة 2: Migration Scripts & Data Migration

#### 2.1 إنشاء Migration الرئيسي
- ملف: `migrations/20251211_complete_system_features.sql`
- يحتوي على جميع تعديلات القاعدة البيانات أعلاه

#### 2.2 Data Migration للبيانات الموجودة
```sql
-- ترحيل deposit_amount إلى جدول payments للفواتير الموجودة
INSERT INTO payments (invoice_id, payment_date, amount, method, type, created_by, created_at)
SELECT 
    id,
    invoice_date,
    deposit_amount,
    payment_method,
    'payment',
    created_by,
    created_at
FROM invoices 
WHERE deposit_amount > 0;

-- تحديث invoice_status للفواتير الموجودة بناءً على payment_status
UPDATE invoices 
SET invoice_status = CASE
    WHEN payment_status = 'paid' AND return_date IS NOT NULL AND return_date < CURDATE() THEN 'closed'
    WHEN payment_status = 'paid' AND collection_date IS NOT NULL AND collection_date < CURDATE() THEN 'out_with_customer'
    WHEN payment_status IN ('partial', 'unpaid') AND deposit_amount > 0 THEN 'reserved'
    ELSE 'reserved'
END
WHERE invoice_status IS NULL OR invoice_status = '';
```

---

### 📋 المرحلة 3: Backend - Core Business Logic

#### 3.1 إنشاء Helper Classes جديدة

**ملف: `app/helpers/invoice_lifecycle.php`**
- `changeInvoiceStatus($invoiceId, $newStatus, $userId, $notes = null)`
- `validateStatusTransition($currentStatus, $newStatus)`
- `deliverInvoice($invoiceId, $userId, $notes = null)`
- `returnInvoice($invoiceId, $userId, $condition, $notes = null)`
- `closeInvoice($invoiceId, $userId, $notes = null)`
- `cancelInvoice($invoiceId, $userId, $reason)`

**ملف: `app/helpers/payment_manager.php`**
- `addPayment($invoiceId, $amount, $method, $type = 'payment', $notes = null)`
- `getInvoicePayments($invoiceId)`
- `calculateTotalPaid($invoiceId)`
- `calculateRemainingBalance($invoiceId)`
- `updatePaymentStatus($invoiceId)`

**ملف: `app/helpers/double_booking_checker.php`**
- `checkProductAvailability($productId, $collectionDate, $returnDate, $excludeInvoiceId = null)`
- `getConflictingBookings($productId, $collectionDate, $returnDate)`
- `isProductAvailableForPeriod($productId, $startDate, $endDate)`

**ملف: `app/helpers/logger.php`**
- `logAction($userId, $actionType, $relatedType, $relatedId, $description)`
- `logInvoiceAction($invoiceId, $action, $description)`
- `logPaymentAction($paymentId, $action, $description)`

#### 3.2 تحديث الـ APIs الموجودة

**تعديل: `public/save_invoice.php`**
- إضافة التحقق من الحجز المزدوج قبل الحفظ
- تحديث invoice_status بناءً على حالة الفاتورة
- تسجيل الإجراء في store_logs

**تعديل: `public/api/invoices.php`**
- إضافة action جديدة: `change_status`
- إضافة action: `deliver`
- إضافة action: `return`
- إضافة action: `close`
- إلغاء DELETE الفعلي واستبداله بـ Soft Delete

**إنشاء: `public/api/payments.php`**
```php
GET  ?action=list&invoice_id={id}     // قائمة المدفوعات
POST ?action=add                       // إضافة دفعة
POST ?action=delete&id={id}            // حذف دفعة (بصلاحية)
GET  ?action=summary&invoice_id={id}   // ملخص المدفوعات
```

**إنشاء: `public/api/receivables.php`**
```php
GET ?action=unpaid_invoices           // الفواتير غير المسددة
GET ?action=aging_report              // تقرير الأعمار
GET ?action=customer_balance&id={id}  // رصيد عميل معين
```

---

### 📋 المرحلة 4: Frontend - واجهات المستخدم

#### 4.1 تحديث صفحة الفاتورة (sales.php)

**إضافات:**
- عمود جديد: `invoice_status` (بألوان مختلفة)
- فلتر حسب invoice_status
- إزالة زر "حذف" واستبداله بـ "إلغاء"
- أيقونات ملونة لكل حالة

#### 4.2 تحديث نموذج الفاتورة (new-invoice.php)

**إضافات:**
- التحقق من توفر الفستان في الفترة المحددة (للإيجار)
- رسالة تحذير إذا كان الفستان محجوزاً

#### 4.3 إنشاء صفحة تفاصيل الفاتورة المحسّنة

**ملف جديد: `public/invoice_details.php`**

**الأقسام:**
1. **معلومات الفاتورة الأساسية**
   - رقم الفاتورة، التاريخ، العميل، نوع العملية

2. **حالة الفاتورة (Status Timeline)**
   - خط زمني يوضح الحالة الحالية والتاريخ
   - أزرار الإجراءات حسب الحالة:
     - إذا كانت `reserved` → زر "تأكيد التسليم"
     - إذا كانت `out_with_customer` → زر "تأكيد الإرجاع"
     - إذا كانت `returned` → زر "إقفال الفاتورة"

3. **المدفوعات**
   - جدول بجميع المدفوعات
   - ملخص: الإجمالي، المدفوع، المتبقي
   - نموذج إضافة دفعة جديدة

4. **العناصر (الفساتين والإكسسوارات)**
   - قائمة بجميع العناصر
   - القياسات والملاحظات

5. **سجل التغييرات (History Log)**
   - جميع التغييرات على الفاتورة
   - من غيّر، متى، ماذا

#### 4.4 إنشاء صفحة أرشيف الفواتير

**ملف جديد: `public/invoices_archive.php`**
- عرض الفواتير: closed, canceled, returned
- فلاتر متقدمة
- إحصائيات الأرشيف

#### 4.5 تحديث صفحة العميل (customer.php)

**إضافة قسم Timeline:**
```html
<div class="customer-timeline">
  <!-- جميع الفواتير -->
  <!-- جميع المدفوعات -->
  <!-- جميع الحجوزات -->
  <!-- مرتبة حسب التاريخ -->
</div>
```

#### 4.6 إنشاء صفحة تقرير الذمم

**ملف جديد: `public/receivables_report.php`**

**الأقسام:**
1. ملخص عام (إجمالي الذمم، عدد الفواتير)
2. جدول الفواتير غير المسددة
3. تجميع حسب العميل
4. تقرير الأعمار (30، 60، 90+ يوم)
5. فلاتر وتصدير Excel/PDF

---

### 📋 المرحلة 5: JavaScript & AJAX

#### 5.1 ملفات JavaScript جديدة

**`assets/js/invoice-lifecycle.js`**
- دوال لتغيير حالة الفاتورة
- Modal للتسليم والإرجاع
- Validation

**`assets/js/payments-manager.js`**
- إضافة دفعة
- عرض المدفوعات
- حساب المتبقي تلقائياً

**`assets/js/double-booking-check.js`**
- التحقق من التوفر عند اختيار التواريخ
- عرض رسائل تحذير

#### 5.2 تحديث الملفات الموجودة

**تعديل: `assets/js/sales.js`**
- دعم الحالات الجديدة
- إزالة DELETE واستبدالها بـ Cancel

---

### 📋 المرحلة 6: الصلاحيات والأمان

#### 6.1 نظام الصلاحيات

**إنشاء: `app/helpers/permissions.php`**
```php
- canCancelInvoice($userId, $invoiceId)
- canDeletePayment($userId, $paymentId)
- canViewCost($userId)
- canChangeInvoiceStatus($userId)
```

#### 6.2 Middleware للتحقق من الصلاحيات
- في كل API endpoint حساس
- تسجيل محاولات الوصول غير المصرح بها

---

### 📋 المرحلة 7: التقارير المحسّنة

#### 7.1 تحديث صفحة التقارير
**تعديل: `public/reports.php`**

**إضافة تقارير جديدة:**
- تقرير الفواتير حسب الحالة
- تقرير المدفوعات اليومية/الشهرية
- تقرير الذمم والأعمار
- تقرير التسليم والإرجاع

---

### 📋 المرحلة 8: الاختبار والتوثيق

#### 8.1 Test Cases
- اختبار منع الحجز المزدوج
- اختبار دورة حياة الفاتورة الكاملة
- اختبار نظام المدفوعات
- اختبار الصلاحيات

#### 8.2 التوثيق
- دليل المستخدم للميزات الجديدة
- دليل المطور للـ APIs الجديدة
- Schema Documentation

---

## ترتيب التنفيذ المقترح

### المجموعة A - الأساسيات (يوم 1)
1. ✅ تحديث قاعدة البيانات
2. ✅ Migration Scripts
3. ✅ Data Migration

### المجموعة B - Backend Core (يوم 2)
4. ✅ Helper Classes
5. ✅ API Updates
6. ✅ Business Logic

### المجموعة C - Frontend Essentials (يوم 3)
7. ✅ صفحة تفاصيل الفاتورة
8. ✅ نظام المدفوعات UI
9. ✅ Delivery/Return Forms

### المجموعة D - Advanced Features (يوم 4)
10. ✅ Double Booking Prevention
11. ✅ Receivables Report
12. ✅ Customer Timeline

### المجموعة E - Polish & Security (يوم 5)
13. ✅ Permissions System
14. ✅ Logging System
15. ✅ Testing & Documentation

---

## ملاحظات هامة

⚠️ **التوافق مع البيانات الموجودة**
- جميع التعديلات يجب أن تحافظ على البيانات الحالية
- Migration Scripts تملأ الحقول الجديدة تلقائياً

⚠️ **Soft Delete**
- لا يوجد حذف فعلي للفواتير
- استخدام invoice_status = 'canceled' أو deleted_at

⚠️ **Multi-Store Compatibility**
- جميع التعديلات تعمل على مستوى قاعدة المتجر فقط
- لا تأثير على Master Database

⚠️ **Backward Compatibility**
- الـ APIs القديمة تظل تعمل
- إضافة endpoints جديدة بدلاً من تعديل القديمة حيثما أمكن

---

## المخرجات النهائية

✅ نظام فواتير كامل مع دورة حياة واضحة
✅ نظام مدفوعات متعدد المراحل
✅ منع الحجز المزدوج تلقائياً
✅ تقارير ذمم وديون شاملة
✅ CRM محسّن لخدمة العميل
✅ نظام صلاحيات وسجلات محكم
✅ واجهات مستخدم احترافية
✅ توافق كامل مع البيانات الموجودة
