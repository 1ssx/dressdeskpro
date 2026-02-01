-- ============================================================================
-- MASTER PLATFORM - SCHEMA ENHANCEMENTS
-- Extends existing tables with new features for enhanced store management
-- ============================================================================

USE `wep_master`;

-- ----------------------------------------------------------------------------
-- ENHANCE STORES TABLE
-- Add soft delete support and last login tracking
-- ----------------------------------------------------------------------------
ALTER TABLE `stores` 
  MODIFY COLUMN `status` ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
  ADD COLUMN `last_login` DATETIME NULL AFTER `status`,
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`,
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`,
  ADD INDEX `idx_deleted_at` (`deleted_at`),
  ADD INDEX `idx_last_login` (`last_login`);

-- Note: This is a migration file. Run it ONCE to update the existing master database.
