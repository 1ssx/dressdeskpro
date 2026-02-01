-- ============================================================================
-- MASTER DATABASE SEED DATA
-- Initial owner user and existing store registration
-- Database: wep_master
-- ============================================================================

USE `wep_master`;

-- ----------------------------------------------------------------------------
-- 1. INSERT INITIAL OWNER USER
-- Note: Replace 'YOUR_PASSWORD_HASH_HERE' with actual hash generated using:
-- php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
-- ----------------------------------------------------------------------------
INSERT INTO `master_users` (`full_name`, `email`, `password`, `role`, `created_at`, `updated_at`)
VALUES (
    'Abdulfattah Esmail Thabit',
    'Abdulfattah1171@gmail.com',
    '$2y$10$ItzwIotpgc/JXQQBqlyXMOZnG2Ni4S/ktP7Mouw0cgoguBO1nfnqC',  -- REPLACE THIS with actual hash
    'owner',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `full_name` = VALUES(`full_name`),
    `role` = VALUES(`role`);

-- ----------------------------------------------------------------------------
-- 2. REGISTER EXISTING STORE "لمسات الأسطورة"
-- This store uses the existing wep02_v2 database
-- ----------------------------------------------------------------------------
INSERT INTO `stores` (`store_name`, `owner_name`, `owner_email`, `database_name`, `activation_code_used`, `status`, `created_at`, `updated_at`)
VALUES (
    'لمسات الأسطورة',
    'Store Owner',  -- Update with actual owner name if known
    'owner@example.com',  -- Update with actual owner email if known
    'wep02_v2',
    NULL,  -- No activation code used (existing store)
    'active',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `status` = VALUES(`status`);

-- ----------------------------------------------------------------------------
-- NOTES:
-- 1. Before running this script, generate the password hash:
--    php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
-- 2. Replace 'YOUR_PASSWORD_HASH_HERE' with the generated hash
-- 3. Update owner_name and owner_email for the existing store if you have that information
-- 4. The existing store will continue using wep02_v2 database
-- ----------------------------------------------------------------------------

