<?php
/**
 * Migration: Example - Add subscription logs to master database
 * Created: 2025-12-07
 * 
 * This is an example migration for the master database.
 * You can delete this file after understanding how it works.
 */

return function($pdo) {
    // UP: Create a subscription_logs table in master database
    $sql = "
        CREATE TABLE IF NOT EXISTS subscription_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_store_id (store_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Tracks subscription-related events'
    ";
    
    $pdo->exec($sql);
};
