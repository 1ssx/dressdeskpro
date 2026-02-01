-- ============================================================================
-- Migration: Add is_locked column to products table
-- Purpose: Allow locking products that are linked to invoices
-- Date: 2025-12-08
-- ============================================================================

-- Add is_locked column to products table if it doesn't exist
-- This column prevents editing/deleting products that are linked to invoices

-- ============================================================================
-- Simple Commands (run these manually in phpMyAdmin if needed):
-- ============================================================================

-- 1. Add is_locked column
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Add status column (if missing)
ALTER TABLE products ADD COLUMN IF NOT EXISTS status ENUM('available','reserved','sold','rented','out_of_stock') NOT NULL DEFAULT 'available';

-- 3. Add created_at column (if missing)
ALTER TABLE products ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 4. Add updated_at column (if missing)
ALTER TABLE products ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 5. Add index for is_locked
CREATE INDEX IF NOT EXISTS idx_products_locked ON products(is_locked);

-- ============================================================================
-- Alternative: For MySQL versions that don't support IF NOT EXISTS on ALTER:
-- ============================================================================
-- 
-- Check if column exists first, then add:
-- 
-- SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'is_locked';
-- 
-- If result is 0, run:
-- ALTER TABLE products ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0;
-- 
-- ============================================================================
