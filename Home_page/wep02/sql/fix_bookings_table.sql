-- ============================================================================
-- FIX BOOKINGS TABLE AND VIEWS
-- This script fixes the bookings table if it was created without booking_type
-- and recreates all views to match the correct schema
-- Database: wep02_v2
-- ============================================================================

USE `wep02_v2`;

-- ----------------------------------------------------------------------------
-- STEP 1: Check and Add booking_type column if missing
-- ----------------------------------------------------------------------------

-- Check if booking_type column exists, if not, add it
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'wep02_v2' 
      AND TABLE_NAME = 'bookings' 
      AND COLUMN_NAME = 'booking_type'
);

-- If column doesn't exist, add it
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `bookings` ADD COLUMN `booking_type` VARCHAR(50) NOT NULL DEFAULT "تجربة" AFTER `invoice_id`;',
    'SELECT "Column booking_type already exists" AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- STEP 2: Drop existing views (if they exist)
-- ----------------------------------------------------------------------------

DROP VIEW IF EXISTS `top_booking_types_view`;
DROP VIEW IF EXISTS `weekly_bookings_view`;
DROP VIEW IF EXISTS `daily_bookings_view`;

-- ----------------------------------------------------------------------------
-- STEP 3: Recreate all views with correct schema
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

-- ----------------------------------------------------------------------------
-- VERIFICATION: Show table structure
-- ----------------------------------------------------------------------------

SELECT 'Bookings table structure:' AS info;
DESCRIBE bookings;

SELECT 'Views created successfully!' AS status;

