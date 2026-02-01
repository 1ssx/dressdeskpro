-- ============================================================================
-- BOOKINGS TABLE CREATION
-- Wedding Dress Sales Management System - Bookings Module
-- Database: wep02_v2
-- ============================================================================

USE `wep02_v2`;

-- ----------------------------------------------------------------------------
-- 1. BOOKINGS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `invoice_id` INT UNSIGNED NULL,
  `booking_type` VARCHAR(50) NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'late') NOT NULL DEFAULT 'pending',
  `booking_date` DATETIME NOT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_booking_date` (`booking_date`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_booking_type` (`booking_type`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_bookings_customer` 
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_invoice` 
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Booking appointments for dress trials, measurements, pickups, etc.';

-- ----------------------------------------------------------------------------
-- 2. SQL VIEWS FOR REPORTS
-- ----------------------------------------------------------------------------

-- Daily Bookings View: Bookings grouped by date
CREATE OR REPLACE VIEW `daily_bookings_view` AS
SELECT 
    DATE(booking_date) AS booking_day,
    COUNT(*) AS total_bookings,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count,
    COUNT(CASE WHEN status = 'late' THEN 1 END) AS late_count
FROM bookings
GROUP BY DATE(booking_date)
ORDER BY booking_day DESC;

-- Weekly Bookings View: Bookings grouped by week
CREATE OR REPLACE VIEW `weekly_bookings_view` AS
SELECT 
    YEARWEEK(booking_date, 1) AS week_number,
    YEAR(booking_date) AS year,
    MIN(DATE(booking_date)) AS week_start,
    MAX(DATE(booking_date)) AS week_end,
    COUNT(*) AS total_bookings,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count
FROM bookings
GROUP BY YEARWEEK(booking_date, 1), YEAR(booking_date)
ORDER BY year DESC, week_number DESC;

-- Top Booking Types View: Count of bookings by type
CREATE OR REPLACE VIEW `top_booking_types_view` AS
SELECT 
    booking_type,
    COUNT(*) AS total_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count,
    ROUND(COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM bookings), 0), 2) AS percentage
FROM bookings
GROUP BY booking_type
ORDER BY total_count DESC;

