<?php
/**
 * Dashboard Statistics API
 * Returns real-time dashboard metrics from the database
 */

// Load common infrastructure (handles output buffering)
require_once __DIR__ . '/_common/bootstrap.php';

// Load caching helper
require_once __DIR__ . '/../../app/helpers/cache.php';

// Load revenue calculator for cash-based revenue tracking
require_once __DIR__ . '/../../app/helpers/revenue_calculator.php';

try {
    // Use caching to reduce database load
    // Cache key includes store ID for multi-store support
    $storeId = $_SESSION['store_id'] ?? 'unknown';
    $cacheKey = "dashboard_stats_{$storeId}_" . date('Y-m-d_H-i'); // Changes every minute
    
    $data = cache()->remember($cacheKey, function() use ($pdo) {
        // Get today's date in server timezone
        $today = date('Y-m-d');
        
        // Debug: Log today's date (can be removed in production)
        if (function_exists('logError')) {
            logError('Dashboard API - Today date', ['today' => $today]);
        }

        // Initialize Revenue Calculator for CASH-BASED revenue tracking
        $revenueCalculator = new RevenueCalculator($pdo);

        // 1. Daily Sales & Revenue (CORRECTED with RevenueCalculator)
        // ✅ CRITICAL FIX: Use RevenueCalculator for accurate cash-based revenue
        // This now includes BOTH initial deposits AND subsequent payments from "Add Payment" modal
        $todayRevenue = $revenueCalculator->calculateDailyRevenue($today);
        
        // Get invoice count and total sales value separately
        $salesStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(total_price), 0) as daily_sales,
                COUNT(*) as invoice_count
            FROM invoices 
            WHERE DATE(invoice_date) = CURDATE()
        ");
        $salesStmt->execute();
        $salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log query results (can be removed in production)
        if (function_exists('logError')) {
            logError('Dashboard API - Sales data', [
                'today' => $today,
                'daily_sales' => $salesData['daily_sales'] ?? 0,
                'today_revenue_cash_based' => $todayRevenue,
                'invoice_count' => $salesData['invoice_count'] ?? 0
            ]);
        }

        // 2. New Customers (created today)
        $customersStmt = $pdo->prepare("
            SELECT COUNT(*) as new_customers
            FROM customers 
            WHERE DATE(created_at) = CURDATE()
        ");
        $customersStmt->execute();
        $customersData = $customersStmt->fetch(PDO::FETCH_ASSOC);

        // 3. Available Inventory
        $inventoryStmt = $pdo->query("
            SELECT COUNT(*) as available_dresses,
                   COALESCE(SUM(quantity), 0) as total_quantity
            FROM products 
            WHERE quantity > 0
        ");
        $inventoryData = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

        // 4. Today's Bookings
        $bookingsStmt = $pdo->prepare("
            SELECT COUNT(*) as today_bookings
            FROM bookings 
            WHERE DATE(booking_date) = CURDATE()
        ");
        $bookingsStmt->execute();
        $bookingsData = $bookingsStmt->fetch(PDO::FETCH_ASSOC);

        // 5. Growth Percentage (compared to yesterday)
        $yesterdaySalesStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_price), 0) as yesterday_sales
            FROM invoices 
            WHERE DATE(invoice_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $yesterdaySalesStmt->execute();
        $yesterdayData = $yesterdaySalesStmt->fetch(PDO::FETCH_ASSOC);
        
        $todaySales = floatval($salesData['daily_sales'] ?? 0);
        $yesterdaySales = floatval($yesterdayData['yesterday_sales'] ?? 0);
        
        $growth = 0;
        if ($yesterdaySales > 0) {
            // Calculate percentage change: ((today - yesterday) / yesterday) * 100
            $growth = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100;
            $growth = round($growth, 1);
        } elseif ($todaySales > 0 && $yesterdaySales == 0) {
            // If yesterday was 0 and today has sales, show 100% growth
            $growth = 100;
        } elseif ($todaySales == 0 && $yesterdaySales > 0) {
            // If today is 0 and yesterday had sales, show negative growth
            $growth = -100;
        }
        // If both are 0, growth stays 0

        // Prepare response data - ensure all values are properly converted
        $dailySales = floatval($salesData['daily_sales'] ?? 0);
        $invoiceCount = intval($salesData['invoice_count'] ?? 0);
        
        return [
            'daily_sales' => $dailySales,
            'total_sales' => $dailySales, // Alias for clarity
            'today_revenue' => $todayRevenue, // ✅ NOW INCLUDES ALL PAYMENTS (Cash-Based)
            'invoice_count' => $invoiceCount,
            'new_customers' => intval($customersData['new_customers'] ?? 0),
            'available_dresses' => intval($inventoryData['available_dresses'] ?? 0),
            'total_quantity' => intval($inventoryData['total_quantity'] ?? 0),
            'today_bookings' => intval($bookingsData['today_bookings'] ?? 0),
            'growth_percentage' => $growth,
            'date' => date('Y-m-d') // Use current date
        ];
    }, 120); // Cache for 2 minutes (120 seconds)
    
    // Debug: Log final data being sent
    if (function_exists('logError')) {
        logError('Dashboard API - Final response data', $data);
    }

    sendSuccess($data);

} catch (Exception $e) {
    logError('Dashboard API Error', ['message' => $e->getMessage()]);
    sendError('Failed to load dashboard statistics: ' . $e->getMessage(), 500);
}
