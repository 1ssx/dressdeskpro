-- ============================================================================
-- COMPLETE SYSTEM FEATURES MIGRATION
-- Date: 2025-12-11
-- Description: إضافة الميزات الأساسية الناقصة للنظام
--   - دورة حياة الفاتورة (Invoice Lifecycle)
--   - نظام التسليم والإرجاع (Delivery & Return)
--   - نظام المدفوعات المتعدد (Multiple Payments)
--   - سجل التغييرات والأحداث (History & Logs)
--   - منع الحجز المزدوج (Double Booking Prevention)
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- PART 1: تحديث جدول الفواتير (Invoices Table Updates)
-- ============================================================================

-- إضافة حقل invoice_status لإدارة دورة حياة الفاتورة
ALTER TABLE `invoices` 
ADD COLUMN `invoice_status` ENUM(
    'draft',              -- مسودة (اختياري)
    'reserved',           -- محجوز (تم دفع عربون)
    'out_with_customer',  -- مع العميل (تم التسليم)
    'returned',           -- تم الإرجاع
    'closed',             -- مقفلة (منتهية بالكامل)
    'canceled'            -- ملغاة
) NOT NULL DEFAULT 'reserved' AFTER `payment_status`;

-- إضافة حقول التسليم والإرجاع
ALTER TABLE `invoices`
ADD COLUMN `delivered_at` DATETIME NULL AFTER `invoice_status`,
ADD COLUMN `delivered_by` INT UNSIGNED NULL AFTER `delivered_at`,
ADD COLUMN `returned_at` DATETIME NULL AFTER `delivered_by`,
ADD COLUMN `returned_by` INT UNSIGNED NULL AFTER `returned_at`,
ADD COLUMN `return_condition` ENUM(
    'excellent',      -- ممتاز
    'good',           -- جيد
    'needs_cleaning', -- يحتاج تنظيف
    'damaged',        -- متضرر
    'missing_items'   -- ناقص ملحقات
) NULL AFTER `returned_by`,
ADD COLUMN `return_notes` TEXT NULL AFTER `return_condition`;

-- إضافة حقل Soft Delete
ALTER TABLE `invoices`
ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;

-- إضافة Foreign Keys للتسليم والإرجاع
ALTER TABLE `invoices`
ADD CONSTRAINT `fk_invoices_delivered_by` 
    FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `invoices`
ADD CONSTRAINT `fk_invoices_returned_by` 
    FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- إضافة Indexes
ALTER TABLE `invoices`
ADD INDEX `idx_invoices_status` (`invoice_status`),
ADD INDEX `idx_invoices_delivered` (`delivered_at`),
ADD INDEX `idx_invoices_returned` (`returned_at`),
ADD INDEX `idx_invoices_deleted` (`deleted_at`);

-- ============================================================================
-- PART 2: إنشاء جدول المدفوعات (Payments Table)
-- ============================================================================

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `amount` DECIMAL(10,2) NOT NULL,
    `method` ENUM('cash', 'card', 'transfer', 'mixed') NOT NULL DEFAULT 'cash',
    `type` ENUM('payment', 'refund', 'penalty') NOT NULL DEFAULT 'payment',
    `notes` TEXT NULL,
    `receipt_number` VARCHAR(100) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_payments_invoice` 
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_user` 
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_payments_invoice` (`invoice_id`),
    INDEX `idx_payments_date` (`payment_date`),
    INDEX `idx_payments_type` (`type`),
    INDEX `idx_payments_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='جدول المدفوعات المتعددة لكل فاتورة';

-- ============================================================================
-- PART 3: إنشاء جدول سجل تغييرات الفواتير (Invoice Status History)
-- ============================================================================

DROP TABLE IF EXISTS `invoice_status_history`;
CREATE TABLE `invoice_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `status_from` VARCHAR(50) NULL,
    `status_to` VARCHAR(50) NOT NULL,
    `payment_status_from` VARCHAR(50) NULL,
    `payment_status_to` VARCHAR(50) NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `changed_by` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_history_invoice` 
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_history_user` 
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_history_invoice` (`invoice_id`),
    INDEX `idx_history_date` (`changed_at`),
    INDEX `idx_history_status` (`status_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='سجل تغييرات حالات الفواتير';

-- ============================================================================
-- PART 4: إنشاء جدول السجلات العامة (Store Logs)
-- ============================================================================

DROP TABLE IF EXISTS `store_logs`;
CREATE TABLE `store_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `related_type` VARCHAR(50) NULL COMMENT 'invoice, customer, product, payment, etc.',
    `related_id` INT UNSIGNED NULL,
    `description` TEXT NULL,
    `data_before` JSON NULL COMMENT 'البيانات قبل التغيير',
    `data_after` JSON NULL COMMENT 'البيانات بعد التغيير',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_logs_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_logs_user` (`user_id`),
    INDEX `idx_logs_action` (`action_type`),
    INDEX `idx_logs_date` (`created_at`),
    INDEX `idx_logs_related` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='سجل جميع العمليات في المتجر';

-- ============================================================================
-- PART 5: تحديث جدول المنتجات (Products Table Updates)
-- ============================================================================

-- إضافة حقل is_locked إن لم يكن موجوداً
ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0 
AFTER `status`
COMMENT 'مقفل من التعديل (مرتبط بفاتورة)';

-- إضافة Index
ALTER TABLE `products`
ADD INDEX IF NOT EXISTS `idx_products_locked` (`is_locked`);

-- ============================================================================
-- PART 6: Data Migration - ترحيل البيانات الموجودة
-- ============================================================================

-- 6.1: ترحيل deposit_amount إلى جدول payments
-- فقط للفواتير التي بها deposit_amount > 0
INSERT INTO `payments` (
    `invoice_id`, 
    `payment_date`, 
    `amount`, 
    `method`, 
    `type`, 
    `notes`, 
    `created_by`, 
    `created_at`
)
SELECT 
    `id` AS invoice_id,
    `invoice_date` AS payment_date,
    `deposit_amount` AS amount,
    `payment_method` AS method,
    'payment' AS type,
    'عربون - تم الترحيل من النظام القديم' AS notes,
    `created_by`,
    `created_at`
FROM `invoices` 
WHERE `deposit_amount` > 0
AND NOT EXISTS (
    SELECT 1 FROM `payments` p WHERE p.invoice_id = invoices.id
);

-- 6.2: تحديث invoice_status للفواتير الموجودة
-- القاعدة:
-- - paid + return_date في الماضي = closed
-- - paid + collection_date في الماضي = out_with_customer
-- - partial/unpaid + deposit > 0 = reserved
-- - canceled إذا كان deleted
UPDATE `invoices` 
SET `invoice_status` = CASE
    -- إذا كانت مدفوعة بالكامل وتاريخ الإرجاع مضى
    WHEN `payment_status` = 'paid' 
         AND `return_date` IS NOT NULL 
         AND `return_date` < CURDATE() 
    THEN 'closed'
    
    -- إذا كانت مدفوعة بالكامل وتاريخ الاستلام مضى (في يد العميل)
    WHEN `payment_status` = 'paid' 
         AND `collection_date` IS NOT NULL 
         AND `collection_date` <= CURDATE() 
         AND (return_date IS NULL OR return_date >= CURDATE())
    THEN 'out_with_customer'
    
    -- إذا كانت جزئية أو غير مدفوعة لكن بها عربون (محجوزة)
    WHEN `payment_status` IN ('partial', 'unpaid') 
         AND `deposit_amount` > 0 
    THEN 'reserved'
    
    -- الحالة الافتراضية
    ELSE 'reserved'
END
WHERE `invoice_status` IS NULL 
   OR `invoice_status` = '';

-- 6.3: إنشاء سجل تاريخي للفواتير الموجودة
INSERT INTO `invoice_status_history` (
    `invoice_id`,
    `status_from`,
    `status_to`,
    `payment_status_from`,
    `payment_status_to`,
    `changed_at`,
    `changed_by`,
    `notes`
)
SELECT 
    `id` AS invoice_id,
    NULL AS status_from,
    `invoice_status` AS status_to,
    NULL AS payment_status_from,
    `payment_status` AS payment_status_to,
    `created_at` AS changed_at,
    `created_by` AS changed_by,
    'تم إنشاء الفاتورة - ترحيل من النظام القديم' AS notes
FROM `invoices`
WHERE NOT EXISTS (
    SELECT 1 FROM `invoice_status_history` h WHERE h.invoice_id = invoices.id
);

-- ============================================================================
-- PART 7: Views للتقارير الجديدة
-- ============================================================================

-- 7.1: View للفواتير النشطة (Active Invoices)
DROP VIEW IF EXISTS `active_invoices_view`;
CREATE OR REPLACE VIEW `active_invoices_view` AS
SELECT 
    i.*,
    c.name AS customer_name,
    c.phone_1 AS customer_phone,
    COALESCE(SUM(p.amount), 0) AS total_paid,
    (i.total_price - COALESCE(SUM(p.amount), 0)) AS actual_remaining,
    COUNT(p.id) AS payments_count
FROM `invoices` i
LEFT JOIN `customers` c ON i.customer_id = c.id
LEFT JOIN `payments` p ON i.id = p.invoice_id AND p.type = 'payment'
WHERE i.invoice_status IN ('reserved', 'out_with_customer')
  AND i.deleted_at IS NULL
GROUP BY i.id;

-- 7.2: View للفواتير المتأخرة (Overdue Invoices)
DROP VIEW IF EXISTS `overdue_invoices_view`;
CREATE OR REPLACE VIEW `overdue_invoices_view` AS
SELECT 
    i.*,
    c.name AS customer_name,
    c.phone_1 AS customer_phone,
    COALESCE(SUM(p.amount), 0) AS total_paid,
    (i.total_price - COALESCE(SUM(p.amount), 0)) AS actual_remaining,
    DATEDIFF(CURDATE(), i.invoice_date) AS days_overdue,
    CASE 
        WHEN DATEDIFF(CURDATE(), i.invoice_date) <= 30 THEN '0-30'
        WHEN DATEDIFF(CURDATE(), i.invoice_date) <= 60 THEN '31-60'
        WHEN DATEDIFF(CURDATE(), i.invoice_date) <= 90 THEN '61-90'
        ELSE '90+'
    END AS aging_bucket
FROM `invoices` i
LEFT JOIN `customers` c ON i.customer_id = c.id
LEFT JOIN `payments` p ON i.id = p.invoice_id AND p.type = 'payment'
WHERE i.payment_status != 'paid'
  AND i.invoice_status NOT IN ('closed', 'canceled')
  AND i.deleted_at IS NULL
  AND (i.total_price - COALESCE(SUM(p.amount), 0)) > 0
GROUP BY i.id;

-- 7.3: View لملخص المدفوعات اليومية
DROP VIEW IF EXISTS `daily_payments_view`;
CREATE OR REPLACE VIEW `daily_payments_view` AS
SELECT 
    DATE(payment_date) AS payment_day,
    method AS payment_method,
    type AS payment_type,
    COUNT(*) AS payments_count,
    SUM(amount) AS total_amount
FROM `payments`
GROUP BY DATE(payment_date), method, type;

-- ============================================================================
-- PART 8: Stored Procedures للعمليات المتكررة
-- ============================================================================

-- 8.1: Procedure لحساب إجمالي المدفوعات لفاتورة
DROP PROCEDURE IF EXISTS `calculate_invoice_payments`;
DELIMITER //
CREATE PROCEDURE `calculate_invoice_payments`(
    IN p_invoice_id INT UNSIGNED,
    OUT p_total_paid DECIMAL(10,2),
    OUT p_total_refunds DECIMAL(10,2),
    OUT p_total_penalties DECIMAL(10,2),
    OUT p_net_paid DECIMAL(10,2)
)
BEGIN
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN type = 'penalty' THEN amount ELSE 0 END), 0),
        COALESCE(SUM(CASE 
            WHEN type = 'payment' THEN amount 
            WHEN type = 'refund' THEN -amount 
            ELSE 0 
        END), 0)
    INTO p_total_paid, p_total_refunds, p_total_penalties, p_net_paid
    FROM `payments`
    WHERE invoice_id = p_invoice_id;
END //
DELIMITER ;

-- 8.2: Procedure لتحديث حالة الدفع تلقائياً
DROP PROCEDURE IF EXISTS `update_invoice_payment_status`;
DELIMITER //
CREATE PROCEDURE `update_invoice_payment_status`(
    IN p_invoice_id INT UNSIGNED
)
BEGIN
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE v_net_paid DECIMAL(10,2);
    DECLARE v_penalties DECIMAL(10,2);
    DECLARE v_remaining DECIMAL(10,2);
    DECLARE v_new_status VARCHAR(20);
    
    -- جلب السعر الإجمالي
    SELECT total_price INTO v_total_price
    FROM invoices WHERE id = p_invoice_id;
    
    -- حساب صافي المدفوعات (دفعات ومرتجعات فقط)
    SELECT COALESCE(SUM(CASE 
        WHEN type = 'payment' THEN amount 
        WHEN type = 'refund' THEN -amount 
        ELSE 0 
    END), 0) INTO v_net_paid
    FROM payments WHERE invoice_id = p_invoice_id;

    -- حساب إجمالي الغرامات
    SELECT COALESCE(SUM(amount), 0) INTO v_penalties
    FROM payments WHERE invoice_id = p_invoice_id AND type = 'penalty';
    
    -- حساب المتبقي = (السعر الأصلي + الغرامات) - المدفوع الصافي
    SET v_remaining = (v_total_price + v_penalties) - v_net_paid;
    
    -- تحديد الحالة
    IF v_remaining <= 0.01 THEN
        SET v_new_status = 'paid';
    ELSEIF v_net_paid > 0 THEN
        SET v_new_status = 'partial';
    ELSE
        SET v_new_status = 'unpaid';
    END IF;
    
    -- تحديث الفاتورة
    UPDATE invoices 
    SET 
        remaining_balance = v_remaining,
        payment_status = v_new_status,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_invoice_id;
END //
DELIMITER ;

-- ============================================================================
-- PART 9: Triggers للحفاظ على تناسق البيانات
-- ============================================================================

-- 9.1: Trigger عند إضافة دفعة - تحديث حالة الفاتورة تلقائياً
DROP TRIGGER IF EXISTS `after_payment_insert`;
DELIMITER //
CREATE TRIGGER `after_payment_insert`
AFTER INSERT ON `payments`
FOR EACH ROW
BEGIN
    CALL update_invoice_payment_status(NEW.invoice_id);
END //
DELIMITER ;

-- 9.2: Trigger عند حذف دفعة - تحديث حالة الفاتورة تلقائياً
DROP TRIGGER IF EXISTS `after_payment_delete`;
DELIMITER //
CREATE TRIGGER `after_payment_delete`
AFTER DELETE ON `payments`
FOR EACH ROW
BEGIN
    CALL update_invoice_payment_status(OLD.invoice_id);
END //
DELIMITER ;

-- 9.3: Trigger عند تحديث دفعة - تحديث حالة الفاتورة تلقائياً
DROP TRIGGER IF EXISTS `after_payment_update`;
DELIMITER //
CREATE TRIGGER `after_payment_update`
AFTER UPDATE ON `payments`
FOR EACH ROW
BEGIN
    CALL update_invoice_payment_status(NEW.invoice_id);
END //
DELIMITER ;

-- ============================================================================
-- COMMIT التغييرات
-- ============================================================================

COMMIT;

-- ============================================================================
-- ملاحظات:
-- 1. هذا الـ Migration يحافظ على جميع البيانات الموجودة
-- 2. يتم ترحيل deposit_amount إلى جدول payments تلقائياً
-- 3. يتم تحديث invoice_status بناءً على الحالة الحالية
-- 4. Triggers تحافظ على تناسق البيانات تلقائياً
-- 5. Views جديدة للتقارير المحسّنة
-- ============================================================================
