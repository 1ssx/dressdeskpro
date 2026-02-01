<?php
/**
 * Migration: Add Store Logo Support
 * Created: 2025-12-08 19:00:00
 */

return function($pdo) {
    // Check if column exists first to avoid errors
    $stmt = $pdo->prepare("SHOW COLUMNS FROM stores LIKE 'logo_path'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN logo_path VARCHAR(500) NULL AFTER store_name");
    }
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM stores LIKE 'logo_updated_at'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN logo_updated_at DATETIME NULL AFTER logo_path");
        $pdo->exec("ALTER TABLE stores ADD INDEX idx_logo_updated_at (logo_updated_at)");
    }
};
