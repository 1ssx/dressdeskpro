-- migration_id: 20251208_add_store_logo
-- description: Add logo fields to stores table in master database for store branding

USE wep_master;

-- Add logo_path column to stores table
ALTER TABLE stores 
ADD COLUMN logo_path VARCHAR(500) NULL AFTER store_name,
ADD COLUMN logo_updated_at DATETIME NULL AFTER logo_path;

-- Add index for faster lookups
ALTER TABLE stores
ADD INDEX idx_logo_updated_at (logo_updated_at);

-- Set default NULL values (optional but explicit)
UPDATE stores SET logo_path = NULL WHERE logo_path IS NULL;
