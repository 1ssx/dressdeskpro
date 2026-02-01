-- ============================================================================
-- NOTIFICATIONS SYSTEM SCHEMA
-- Smart Notification Center for Financial Movements
-- ============================================================================

-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` enum('payment','expense','invoice','alert','system','low_stock') NOT NULL DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `action_url` varchar(500) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Related invoice/expense/product ID',
  `icon` varchar(50) DEFAULT NULL COMMENT 'FontAwesome icon class',
  `color` varchar(20) DEFAULT NULL COMMENT 'Notification color',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Smart notification system for financial alerts';

COMMIT;
