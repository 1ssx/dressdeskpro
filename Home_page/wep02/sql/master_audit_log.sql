-- ============================================================================
-- MASTER PLATFORM - AUDIT LOG TABLE
-- Comprehensive logging of all critical system actions
-- ============================================================================

USE `wep_master`;

-- ----------------------------------------------------------------------------
-- AUDIT_LOG TABLE
-- Records all critical system operations for security and compliance
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_type` VARCHAR(100) NOT NULL COMMENT 'Type of action performed',
  `action_description` TEXT NULL COMMENT 'Detailed description of the action',
  `admin_id` INT UNSIGNED NULL COMMENT 'Master user who performed the action',
  `admin_name` VARCHAR(255) NULL COMMENT 'Name snapshot for quick reference',
  `store_id` INT UNSIGNED NULL COMMENT 'Related store (if applicable)',
  `store_name` VARCHAR(255) NULL COMMENT 'Store name snapshot',
  `affected_entity` VARCHAR(100) NULL COMMENT 'Entity type affected',
  `affected_entity_id` INT UNSIGNED NULL COMMENT 'ID of affected entity',
  `ip_address` VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
  `user_agent` TEXT NULL COMMENT 'Browser/client information',
  `metadata` JSON NULL COMMENT 'Additional context data',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_store_id` (`store_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_affected_entity` (`affected_entity`, `affected_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for all critical system operations';

-- Example action_type values:
-- 'store_created', 'store_suspended', 'store_deleted_soft', 'store_deleted_hard',
-- 'license_created', 'license_used', 'license_expired', 'license_deleted',
-- 'impersonation_login', 'impersonation_logout', 'status_changed', etc.
