-- ============================================================================
-- SIMPLE FIX: Add booking_type column and recreate views
-- Use this if the above script doesn't work
-- Database: wep02_v2
-- ============================================================================

USE `wep02_v2`;

-- Step 1: Add booking_type column if it doesn't exist
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `booking_type` VARCHAR(50) NOT NULL DEFAULT 'تجربة' 
AFTER `invoice_id`;

-- Step 2: Drop existing views
DROP VIEW IF EXISTS `top_booking_types_view`;
DROP VIEW IF EXISTS `weekly_bookings_view`;
DROP VIEW IF EXISTS `daily_bookings_view`;

-- Step 3: Recreate daily_bookings_view
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

-- Step 4: Recreate weekly_bookings_view
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

-- Step 5: Recreate top_booking_types_view (FIXED)
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

