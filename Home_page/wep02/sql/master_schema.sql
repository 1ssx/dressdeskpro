-- ============================================================================
-- MASTER PLATFORM DATABASE SCHEMA
-- Multi-Store SaaS System - Central Control Database
-- Database: wep_master
-- ============================================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `wep_master` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `wep_master`;

-- ----------------------------------------------------------------------------
-- 1. MASTER_USERS TABLE
-- Platform owner and support staff accounts
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `master_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('owner', 'support') NOT NULL DEFAULT 'support',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Platform owner and support staff accounts';

-- ----------------------------------------------------------------------------
-- 2. STORES TABLE
-- Registry of all stores in the platform
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_name` VARCHAR(255) NOT NULL,
  `owner_name` VARCHAR(255) NOT NULL,
  `owner_email` VARCHAR(255) NOT NULL,
  `database_name` VARCHAR(255) NOT NULL,
  `activation_code_used` VARCHAR(255) NULL,
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_store_name` (`store_name`),
  INDEX `idx_database_name` (`database_name`),
  INDEX `idx_status` (`status`),
  INDEX `idx_owner_email` (`owner_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registry of all stores in the platform';

-- ----------------------------------------------------------------------------
-- 3. LICENSE_KEYS TABLE
-- Activation codes for store registration
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `license_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NOT NULL UNIQUE,
  `status` ENUM('unused', 'used', 'expired') NOT NULL DEFAULT 'unused',
  `max_uses` INT UNSIGNED NOT NULL DEFAULT 1,
  `used_by_store_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_code` (`code`),
  INDEX `idx_status` (`status`),
  INDEX `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_license_keys_store` 
    FOREIGN KEY (`used_by_store_id`) REFERENCES `stores` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Activation codes for store registration';

