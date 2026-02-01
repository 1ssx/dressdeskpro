-- ============================================================================
-- PASSWORD RESETS TABLE SCHEMA
-- For Store Databases (each tenant database needs this table)
-- ============================================================================

-- Add columns to users table for password reset OTP
-- Run this on each store database (wep_store_X)

-- Option 1: Add columns to existing users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `reset_otp` VARCHAR(6) DEFAULT NULL COMMENT 'Password reset OTP code',
ADD COLUMN IF NOT EXISTS `otp_expiry` DATETIME DEFAULT NULL COMMENT 'OTP expiration time',
ADD COLUMN IF NOT EXISTS `otp_attempts` INT UNSIGNED DEFAULT 0 COMMENT 'Number of OTP verification attempts',
ADD COLUMN IF NOT EXISTS `otp_request_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of OTP requests (for rate limiting)',
ADD COLUMN IF NOT EXISTS `last_otp_request` DATETIME DEFAULT NULL COMMENT 'Last OTP request time (for rate limiting)';

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS `idx_users_phone` ON `users` (`phone`);
CREATE INDEX IF NOT EXISTS `idx_users_otp_expiry` ON `users` (`otp_expiry`);

-- ============================================================================
-- FOR MASTER DATABASE (wep_master)
-- Similar structure for master_users table
-- ============================================================================

-- Note: Run this on wep_master database
-- ALTER TABLE `master_users` 
-- ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL COMMENT 'Phone number for password recovery',
-- ADD COLUMN IF NOT EXISTS `reset_otp` VARCHAR(6) DEFAULT NULL,
-- ADD COLUMN IF NOT EXISTS `otp_expiry` DATETIME DEFAULT NULL,
-- ADD COLUMN IF NOT EXISTS `otp_attempts` INT UNSIGNED DEFAULT 0,
-- ADD COLUMN IF NOT EXISTS `otp_request_count` INT UNSIGNED DEFAULT 0,
-- ADD COLUMN IF NOT EXISTS `last_otp_request` DATETIME DEFAULT NULL;

