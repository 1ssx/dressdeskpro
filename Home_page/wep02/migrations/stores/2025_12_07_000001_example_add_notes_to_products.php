<?php
/**
 * Migration: Example - Add notes column to products
 * Created: 2025-12-07
 * 
 * This is an example migration to demonstrate the system.
 * You can delete this file after understanding how it works.
 */

return function($pdo) {
    // UP: Add a notes column to products table
    $sql = "
        ALTER TABLE products 
        ADD COLUMN notes TEXT DEFAULT NULL 
        COMMENT 'Additional notes about the product'
    ";
    
    $pdo->exec($sql);
    
    // You can execute multiple statements:
    // $pdo->exec("ALTER TABLE products ADD COLUMN field1 VARCHAR(255)");
    // $pdo->exec("ALTER TABLE customers ADD COLUMN field2 INT DEFAULT 0");
    
    // You can also run complex queries:
    // $pdo->exec("
    //     CREATE TABLE IF NOT EXISTS new_table (
    //         id INT AUTO_INCREMENT PRIMARY KEY,
    //         name VARCHAR(255) NOT NULL,
    //         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    //     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    // ");
};
