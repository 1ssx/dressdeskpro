-- ============================================================================
-- REPAIR SCRIPT FOR EXISTING BROKEN TENANT STORES
-- This script brings existing tenant databases up to parity with the reference schema
-- Safe to run multiple times (idempotent)
-- ============================================================================
-- 
-- Usage:
-- 1. Identify the broken tenant database (e.g., wep_store_35)
-- 2. Run: USE wep_store_35;
-- 3. Execute this entire script
-- 4. Verify: SELECT invoice_status FROM invoices LIMIT 1;
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- PART 1: Fix Invoices Table - Add Missing Columns
-- ============================================================================

SET @dbname = DATABASE();
SET @tablename = "invoices";

-- Add invoice_status if missing
SET @columnname = "invoice_status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column invoice_status already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `invoice_status` ENUM('draft','reserved','out_with_customer','returned','closed','canceled') NOT NULL DEFAULT 'reserved' AFTER `payment_status`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add delivered_at if missing
SET @columnname = "delivered_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column delivered_at already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `delivered_at` DATETIME NULL AFTER `invoice_status`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add delivered_by if missing
SET @columnname = "delivered_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column delivered_by already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `delivered_by` INT UNSIGNED NULL AFTER `delivered_at`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add returned_at if missing
SET @columnname = "returned_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column returned_at already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `returned_at` DATETIME NULL AFTER `delivered_by`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add returned_by if missing
SET @columnname = "returned_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column returned_by already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `returned_by` INT UNSIGNED NULL AFTER `returned_at`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add return_condition if missing
SET @columnname = "return_condition";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column return_condition already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `return_condition` ENUM('excellent','good','needs_cleaning','damaged','missing_items') NULL AFTER `returned_by`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add return_notes if missing
SET @columnname = "return_notes";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column return_notes already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `return_notes` TEXT NULL AFTER `return_condition`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add deleted_at if missing
SET @columnname = "deleted_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column deleted_at already exists' AS result;",
  "ALTER TABLE invoices ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- PART 2: Add Missing Foreign Keys and Indexes to Invoices
-- ============================================================================

-- Add invoice_status index (safe - will skip if exists)
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND index_name = 'idx_invoices_invoice_status');
SET @preparedStatement = IF(@indexExists > 0,
    "SELECT 'Index idx_invoices_invoice_status already exists' AS result;",
    "ALTER TABLE invoices ADD INDEX `idx_invoices_invoice_status` (`invoice_status`);"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add delivered_at index
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND index_name = 'idx_invoices_delivered');
SET @preparedStatement = IF(@indexExists > 0,
    "SELECT 'Index idx_invoices_delivered already exists' AS result;",
    "ALTER TABLE invoices ADD INDEX `idx_invoices_delivered` (`delivered_at`);"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add returned_at index
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND index_name = 'idx_invoices_returned');
SET @preparedStatement = IF(@indexExists > 0,
    "SELECT 'Index idx_invoices_returned already exists' AS result;",
    "ALTER TABLE invoices ADD INDEX `idx_invoices_returned` (`returned_at`);"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add deleted_at index
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND index_name = 'idx_invoices_deleted');
SET @preparedStatement = IF(@indexExists > 0,
    "SELECT 'Index idx_invoices_deleted already exists' AS result;",
    "ALTER TABLE invoices ADD INDEX `idx_invoices_deleted` (`deleted_at`);"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for delivered_by (safe - will skip if exists)
SET @fkExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND constraint_name = 'fk_invoices_delivered_by');
SET @preparedStatement = IF(@fkExists > 0,
    "SELECT 'FK fk_invoices_delivered_by already exists' AS result;",
    "ALTER TABLE invoices ADD CONSTRAINT `fk_invoices_delivered_by` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for returned_by
SET @fkExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE table_schema = @dbname AND table_name = 'invoices' AND constraint_name = 'fk_invoices_returned_by');
SET @preparedStatement = IF(@fkExists > 0,
    "SELECT 'FK fk_invoices_returned_by already exists' AS result;",
    "ALTER TABLE invoices ADD CONSTRAINT `fk_invoices_returned_by` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- PART 3: Create Missing Tables
-- ============================================================================

-- Create payments table if missing
CREATE TABLE IF NOT EXISTS `payments` (
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

-- Create invoice_status_history table if missing
CREATE TABLE IF NOT EXISTS `invoice_status_history` (
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

-- Create store_logs table if missing
CREATE TABLE IF NOT EXISTS `store_logs` (
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

-- ============================================================================
-- PART 4: Fix Products Table - Add is_locked if missing
-- ============================================================================

SET @tablename = "products";
SET @columnname = "is_locked";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column is_locked already exists in products' AS result;",
  "ALTER TABLE products ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status` COMMENT 'مقفل من التعديل (مرتبط بفاتورة)';"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add is_locked index to products
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = @dbname AND table_name = 'products' AND index_name = 'idx_products_locked');
SET @preparedStatement = IF(@indexExists > 0,
    "SELECT 'Index idx_products_locked already exists' AS result;",
    "ALTER TABLE products ADD INDEX `idx_products_locked` (`is_locked`);"
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- PART 5: Data Migration (if needed)
-- ============================================================================

-- Set invoice_status for existing invoices if currently NULL
UPDATE `invoices` 
SET `invoice_status` = 'reserved'
WHERE `invoice_status` IS NULL OR `invoice_status` = '';

COMMIT;

-- ============================================================================
-- VERIFICATION QUERIES (Run these manually after the script)
-- ============================================================================
-- SELECT 'Invoices Table Columns' AS Check_Type;
-- SHOW COLUMNS FROM invoices LIKE 'invoice_status';
-- 
-- SELECT 'Payments Table Exists' AS Check_Type;
-- SHOW TABLES LIKE 'payments';
-- 
-- SELECT 'Invoice Status History Table Exists' AS Check_Type;
-- SHOW TABLES LIKE 'invoice_status_history';
-- 
-- SELECT 'Store Logs Table Exists' AS Check_Type;
-- SHOW TABLES LIKE 'store_logs';
-- ============================================================================
