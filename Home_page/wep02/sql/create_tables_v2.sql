-- ============================================================================
-- STORE DATABASE SCHEMA
-- Complete schema for new store databases
-- This schema matches the actual wep02_v2 database structure exactly
-- Database naming: wep_store_{ID} where ID is from stores table
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- 1. جداول النظام الأساسية (بدون تبعيات)
-- ============================================================================

-- جدول المستخدمين
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System users with authentication';

-- جدول التصنيفات
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#3498db',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product categories for inventory classification';

-- جدول الموردين
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suppliers and vendors for inventory sourcing';

-- جدول فئات المصروفات
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exp_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categories for classifying expenses';

-- جدول العملاء
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `phone_1` varchar(20) NOT NULL,
  `phone_2` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `type` enum('new','regular','vip') NOT NULL DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone1` (`phone_1`),
  KEY `idx_customers_phone2` (`phone_2`),
  KEY `idx_customers_type` (`type`),
  KEY `idx_customers_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer information and contact details';

-- ============================================================================
-- 2. الجداول الرئيسية (تعتمد على الجداول السابقة)
-- ============================================================================

-- جدول المنتجات
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `size` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `fabric_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_quantity` int(11) NOT NULL DEFAULT 5,
  `status` enum('available','reserved','sold','rented','out_of_stock') NOT NULL DEFAULT 'available',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مقفل من التعديل (مرتبط بفاتورة)',
  `description` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_code` (`code`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_supplier` (`supplier_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_locked` (`is_locked`),
  KEY `idx_products_quantity` (`quantity`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product inventory with pricing and stock levels';

-- جدول الفواتير
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `operation_type` enum('sale','rent','design','design-sale','design-rent') NOT NULL,
  `payment_method` enum('cash','card','transfer','mixed') NOT NULL DEFAULT 'cash',
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `invoice_status` enum('draft','reserved','out_with_customer','returned','closed','canceled') NOT NULL DEFAULT 'reserved',
  `delivered_at` datetime DEFAULT NULL,
  `delivered_by` int(10) UNSIGNED DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `returned_by` int(10) UNSIGNED DEFAULT NULL,
  `return_condition` enum('excellent','good','needs_cleaning','damaged','missing_items') DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `wedding_date` date DEFAULT NULL,
  `collection_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  KEY `idx_invoices_customer` (`customer_id`),
  KEY `idx_invoices_date` (`invoice_date`),
  KEY `idx_invoices_status` (`payment_status`),
  KEY `idx_invoices_invoice_status` (`invoice_status`),
  KEY `idx_invoices_delivered` (`delivered_at`),
  KEY `idx_invoices_returned` (`returned_at`),
  KEY `idx_invoices_deleted` (`deleted_at`),
  KEY `idx_invoices_created` (`created_at`),
  KEY `fk_invoices_user` (`created_by`),
  KEY `fk_invoices_delivered_by` (`delivered_by`),
  KEY `fk_invoices_returned_by` (`returned_by`),
  CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_delivered_by` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_returned_by` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales invoices and transactions';

-- جدول الحجوزات
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_type` varchar(50) NOT NULL DEFAULT 'تجربة',
  `booking_date` date NOT NULL,
  `event_date` date DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bookings_customer` (`customer_id`),
  KEY `idx_bookings_date` (`booking_date`),
  KEY `idx_bookings_status` (`status`),
  CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer bookings and reservations';

-- جدول المصروفات
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_category` (`category`),
  KEY `idx_expenses_created` (`created_at`),
  KEY `fk_expenses_user` (`created_by`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily business expenses tracking';

-- ============================================================================
-- 3. الجداول الفرعية (تعتمد على الجداول الرئيسية)
-- ============================================================================

-- جدول عناصر الفاتورة
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `item_type` enum('product','custom_dress','accessory','service') NOT NULL DEFAULT 'custom_dress',
  `item_name` varchar(200) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `measurements` text DEFAULT NULL COMMENT 'Stores measurements as JSON text for compatibility',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_invoice` (`invoice_id`),
  KEY `idx_items_product` (`product_id`),
  CONSTRAINT `fk_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for each invoice - supports multiple items per invoice';

-- جدول المدفوعات
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card','transfer','mixed') NOT NULL DEFAULT 'cash',
  `type` enum('payment','refund','penalty') NOT NULL DEFAULT 'payment',
  `notes` text DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_date` (`payment_date`),
  KEY `idx_payments_type` (`type`),
  KEY `idx_payments_created` (`created_at`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المدفوعات المتعددة لكل فاتورة';

-- جدول سجل تغييرات الفواتير
DROP TABLE IF EXISTS `invoice_status_history`;
CREATE TABLE `invoice_status_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `status_from` varchar(50) DEFAULT NULL,
  `status_to` varchar(50) NOT NULL,
  `payment_status_from` varchar(50) DEFAULT NULL,
  `payment_status_to` varchar(50) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_history_invoice` (`invoice_id`),
  KEY `idx_history_date` (`changed_at`),
  KEY `idx_history_status` (`status_to`),
  CONSTRAINT `fk_history_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل تغييرات حالات الفواتير';

-- جدول السجلات العامة
DROP TABLE IF EXISTS `store_logs`;
CREATE TABLE `store_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `related_type` varchar(50) DEFAULT NULL COMMENT 'invoice, customer, product, payment, etc.',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `data_before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_before`)),
  `data_after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_after`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_action` (`action_type`),
  KEY `idx_logs_date` (`created_at`),
  KEY `idx_logs_related` (`related_type`,`related_id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل جميع العمليات في المتجر';

-- جدول حركات المخزون
DROP TABLE IF EXISTS `inventory_movements`;
CREATE TABLE `inventory_movements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(10) UNSIGNED NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` enum('purchase','sale','return','adjustment','initial') DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movements_product` (`product_id`),
  KEY `idx_movements_type` (`type`),
  KEY `idx_movements_created` (`created_at`),
  KEY `fk_movements_user` (`created_by`),
  CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory movement history for stock tracking';

-- ============================================================================
-- 4. العروض (Views)
-- ============================================================================

-- عرض الحجوزات اليومية
DROP VIEW IF EXISTS `daily_bookings_view`;
CREATE OR REPLACE VIEW `daily_bookings_view` AS 
SELECT 
    cast(`bookings`.`booking_date` as date) AS `booking_day`,
    count(0) AS `total_bookings`,
    count(case when `bookings`.`status` = 'pending' then 1 end) AS `pending_count`,
    count(case when `bookings`.`status` = 'confirmed' then 1 end) AS `confirmed_count`,
    count(case when `bookings`.`status` = 'completed' then 1 end) AS `completed_count`,
    count(case when `bookings`.`status` = 'cancelled' then 1 end) AS `cancelled_count`,
    count(case when `bookings`.`status` = 'late' then 1 end) AS `late_count` 
FROM `bookings` 
GROUP BY cast(`bookings`.`booking_date` as date);

-- عرض أفضل أنواع الحجوزات
DROP VIEW IF EXISTS `top_booking_types_view`;
CREATE OR REPLACE VIEW `top_booking_types_view` AS 
SELECT 
    `bookings`.`booking_type` AS `booking_type`,
    count(0) AS `total_count`,
    count(case when `bookings`.`status` = 'pending' then 1 end) AS `pending_count`,
    count(case when `bookings`.`status` = 'confirmed' then 1 end) AS `confirmed_count`,
    count(case when `bookings`.`status` = 'completed' then 1 end) AS `completed_count`,
    count(case when `bookings`.`status` = 'cancelled' then 1 end) AS `cancelled_count`,
    round(count(0) * 100.0 / nullif((select count(0) from `bookings`),0),2) AS `percentage` 
FROM `bookings` 
GROUP BY `bookings`.`booking_type`;

-- عرض الحجوزات الأسبوعية
DROP VIEW IF EXISTS `weekly_bookings_view`;
CREATE OR REPLACE VIEW `weekly_bookings_view` AS 
SELECT 
    yearweek(`bookings`.`booking_date`,1) AS `week_number`,
    year(`bookings`.`booking_date`) AS `year`,
    min(cast(`bookings`.`booking_date` as date)) AS `week_start`,
    max(cast(`bookings`.`booking_date` as date)) AS `week_end`,
    count(0) AS `total_bookings`,
    count(case when `bookings`.`status` = 'confirmed' then 1 end) AS `confirmed_count`,
    count(case when `bookings`.`status` = 'completed' then 1 end) AS `completed_count`,
    count(case when `bookings`.`status` = 'cancelled' then 1 end) AS `cancelled_count` 
FROM `bookings` 
GROUP BY yearweek(`bookings`.`booking_date`,1), year(`bookings`.`booking_date`);



-- ============================================================================
-- 5. إدخال البيانات الافتراضية
-- ============================================================================

-- الفئات الافتراضية
INSERT IGNORE INTO `categories` (`name`, `slug`, `color`) VALUES
('فساتين زفاف', 'wedding-dresses', '#e84393'),
('فساتين سهرة', 'evening-dresses', '#6c5ce7'),
('طرح', 'veils', '00cec9'),
('اكسسوارات', 'accessories', '#fdcb6e'),
('عبايات', 'abayas', '#2d3436');

-- فئات المصروفات الافتراضية
INSERT IGNORE INTO `expense_categories` (`name`) VALUES
('إيجار'),
('كهرباء'),
('رواتب'),
('مشتريات'),
('صيانة'),
('نثريات'),
('تسويق'),
('ضيافة');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
