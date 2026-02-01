<?php
/**
 * Migration: Add is_locked column to products table
 * Created: 2025-12-08
 * 
 * This migration adds the is_locked column to products table
 * to support auto-locking of inventory items when linked to invoices
 */

return function($pdo) {
    // Add is_locked column to products table
    $pdo->exec("
        ALTER TABLE products 
        ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status
    ");
    
    // Add index for performance
    $pdo->exec("
        ALTER TABLE products 
        ADD INDEX idx_products_locked (is_locked)
    ");
    
    // Update existing products that are linked to invoices to be locked
    $pdo->exec("
        UPDATE products p
        SET p.is_locked = 1
        WHERE EXISTS (
            SELECT 1 FROM invoice_items ii 
            WHERE ii.product_id = p.id
        )
    ");
};
