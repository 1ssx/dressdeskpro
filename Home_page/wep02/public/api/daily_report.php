<?php
/**
 * Daily Report API - Multi-Store Support
 * Generates a COMPREHENSIVE summary including all sections:
 * - Sales/Invoices
 * - Expenses
 * - Customers
 * - Products/Inventory
 * - Bookings
 */

// Load common infrastructure (handles output buffering, auth, database)
require_once __DIR__ . '/_common/bootstrap.php';

try {
    // Get date (default is today)
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Support date range
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    // If date range is provided, use it; otherwise use single date
    if ($dateFrom && $dateTo) {
        $startDate = $dateFrom;
        $endDate = $dateTo;
    } else {
        $startDate = $date;
        $endDate = $date;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendError('Invalid date format. Use YYYY-MM-DD', 400);
        return;
    }
    
    // ================================================================
    // 1. INVOICES (Sales) for the date range
    // ================================================================
    $salesStmt = $pdo->prepare("
        SELECT 
            i.id,
            i.invoice_number,
            i.invoice_date,
            i.total_price,
            i.deposit_amount,
            i.remaining_balance,
            i.payment_status,
            i.operation_type,
            c.name as customer_name,
            c.phone_1 as customer_phone
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE DATE(i.invoice_date) BETWEEN ? AND ?
        ORDER BY i.invoice_date DESC, i.id DESC
    ");
    $salesStmt->execute([$startDate, $endDate]);
    $invoices = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 2. EXPENSES for the date range
    // ================================================================
    $expensesStmt = $pdo->prepare("
        SELECT 
            id,
            expense_date,
            amount,
            category,
            description
        FROM expenses
        WHERE DATE(expense_date) BETWEEN ? AND ?
        ORDER BY expense_date DESC, id DESC
    ");
    $expensesStmt->execute([$startDate, $endDate]);
    $expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // 3. CUSTOMERS (new or updated in date range)
    // ================================================================
    $newCustomers = [];
    $totalCustomers = 0;
    try {
        $customersStmt = $pdo->prepare("
            SELECT 
                id,
                name,
                phone_1,
                phone_2,
                address,
                type,
                created_at
            FROM customers
            WHERE DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $customersStmt->execute([$startDate, $endDate]);
        $newCustomers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total customers count
        $totalCustomersStmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
        $totalCustomers = $totalCustomersStmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        // Table or column doesn't exist - skip
        error_log("Customers query failed (may be missing created_at column): " . $e->getMessage());
    }
    
    // ================================================================
    // 4. PRODUCTS/INVENTORY (new or updated in date range)
    // ================================================================
    $newProducts = [];
    $inventoryStats = ['total_products' => 0, 'total_value' => 0, 'total_quantity' => 0];
    try {
        $productsStmt = $pdo->prepare("
            SELECT 
                p.id,
                p.code,
                p.name,
                p.price,
                p.cost,
                p.size,
                p.color,
                p.quantity,
                p.status,
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at DESC
        ");
        $productsStmt->execute([$startDate, $endDate]);
        $newProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total products count and inventory value
        $inventoryStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                COALESCE(SUM(price * quantity), 0) as total_value,
                COALESCE(SUM(quantity), 0) as total_quantity
            FROM products
        ");
        $inventoryStats = $inventoryStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table or column doesn't exist - skip
        error_log("Products query failed (may be missing created_at column): " . $e->getMessage());
    }
    
    // ================================================================
    // 5. BOOKINGS for the date range
    // ================================================================
    $bookings = [];
    $bookingsByType = [];
    try {
        $bookingsStmt = $pdo->prepare("
            SELECT 
                b.id,
                b.booking_type,
                b.booking_date,
                b.status,
                b.notes,
                c.name as customer_name,
                c.phone_1 as customer_phone
            FROM bookings b
            LEFT JOIN customers c ON b.customer_id = c.id
            WHERE DATE(b.booking_date) BETWEEN ? AND ?
            ORDER BY b.booking_date DESC, b.id DESC
        ");
        $bookingsStmt->execute([$startDate, $endDate]);
        $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get bookings stats by type
        $bookingTypesStmt = $pdo->prepare("
            SELECT 
                booking_type,
                COUNT(*) as count
            FROM bookings
            WHERE DATE(booking_date) BETWEEN ? AND ?
            GROUP BY booking_type
        ");
        $bookingTypesStmt->execute([$startDate, $endDate]);
        $bookingsByType = $bookingTypesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist - skip
        error_log("Bookings query failed: " . $e->getMessage());
    }
    
    // ================================================================
    // 6. PAYMENTS - ORDER PAYMENTS (CRITICAL FIX)
    // ================================================================
    // THIS IS THE MISSING LINK! All payments added via "Order Actions" modal
    // are stored in the 'payments' table and MUST be included in financial reports
    $payments = [];
    $totalPaymentsAmount = 0;
    
    try {
        $paymentsStmt = $pdo->prepare("
            SELECT 
                p.id,
                p.invoice_id,
                p.payment_date,
                p.amount,
                p.method,
                p.type,
                p.notes,
                i.invoice_number,
                c.name as customer_name
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE DATE(p.payment_date) BETWEEN ? AND ?
            AND p.type = 'payment'
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $paymentsStmt->execute([$startDate, $endDate]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total from payments table
        foreach ($payments as $payment) {
            $totalPaymentsAmount += floatval($payment['amount']);
        }
        
        error_log("FINANCIAL REPORT: Found " . count($payments) . " payments totaling {$totalPaymentsAmount} SAR for date range {$startDate} to {$endDate}");
        
    } catch (PDOException $e) {
        // Table might not exist in older versions
        error_log("Payments query failed (table may not exist): " . $e->getMessage());
    }
    
    // ================================================================
    // 7. Calculate TOTALS (CORRECTED)
    // ================================================================
    $totalSales = 0;
    $totalRevenue = 0;
    $totalDeposits = 0; // Legacy deposits from invoices
    $totalExpenses = 0;
    
    foreach ($invoices as $invoice) {
        $totalSales += floatval($invoice['total_price']);
        // Legacy deposit_amount (for backwards compatibility)
        $totalDeposits += floatval($invoice['deposit_amount']);
    }

    
    foreach ($expenses as $expense) {
        $totalExpenses += floatval($expense['amount']);
    }
    
    // CRITICAL: Total revenue = Legacy deposits + All payments from payments table
    // This ensures order payments added via "Order Actions" are included
    $totalRevenue = $totalDeposits + $totalPaymentsAmount;
    
    $netIncome = $totalRevenue - $totalExpenses;
    $remainingBalance = $totalSales - $totalRevenue;
    
    error_log("FINANCIAL REPORT TOTALS: Sales={$totalSales}, LegacyDeposits={$totalDeposits}, Payments={$totalPaymentsAmount}, TotalRevenue={$totalRevenue}, Expenses={$totalExpenses}, NetIncome={$netIncome}");
    
    // ================================================================
    // 8. Prepare COMPREHENSIVE response
    // ================================================================
    $response = [
        'date' => $startDate,
        'date_from' => $startDate,
        'date_to' => $endDate,
        
        // Main data arrays
        'invoices' => $invoices,
        'expenses' => $expenses,
        'payments' => $payments, // ← NEW! Order payments for transparency
        'customers' => $newCustomers,
        'products' => $newProducts,
        'bookings' => $bookings,
        
        // Summary statistics
        'summary' => [
            'total_sales_value' => $totalSales,
            'total_income' => $totalRevenue, // Now includes ALL payments
            'total_legacy_deposits' => $totalDeposits, // For transparency
            'total_order_payments' => $totalPaymentsAmount, // NEW! For transparency
            'total_expenses' => $totalExpenses,
            'net_cash' => $netIncome,
            'remaining_balance' => $remainingBalance,
            
            'invoices_count' => count($invoices),
            'expenses_count' => count($expenses),
            'payments_count' => count($payments), // NEW!
            'new_customers_count' => count($newCustomers),
            'new_products_count' => count($newProducts),
            'bookings_count' => count($bookings),
            
            'total_customers' => (int)$totalCustomers,
            'total_products' => (int)$inventoryStats['total_products'],
            'inventory_value' => floatval($inventoryStats['total_value']),
            'inventory_quantity' => (int)$inventoryStats['total_quantity']
        ],
        
        // Bookings breakdown
        'bookings_by_type' => $bookingsByType,
        
        // Backwards compatibility
        'sales' => $invoices,
        'totals' => [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue, // Now includes ALL payments
            'total_expenses' => $totalExpenses,
            'net_income' => $netIncome
        ],
        
        // Data wrapper for print function
        'data' => [
            'summary' => [
                'total_sales_value' => $totalSales,
                'total_income' => $totalRevenue, // Now includes ALL payments
                'total_legacy_deposits' => $totalDeposits,
                'total_order_payments' => $totalPaymentsAmount,
                'total_expenses' => $totalExpenses,
                'net_cash' => $netIncome,
                'remaining_balance' => $remainingBalance,
                'invoices_count' => count($invoices),
                'expenses_count' => count($expenses),
                'payments_count' => count($payments),
                'new_customers_count' => count($newCustomers),
                'new_products_count' => count($newProducts),
                'bookings_count' => count($bookings),
                'total_customers' => (int)$totalCustomers,
                'total_products' => (int)$inventoryStats['total_products'],
                'inventory_value' => floatval($inventoryStats['total_value'])
            ],
            'invoices' => $invoices,
            'expenses' => $expenses,
            'payments' => $payments, // NEW! Available for rendering in reports
            'customers' => $newCustomers,
            'products' => $newProducts,
            'bookings' => $bookings,
            'bookings_by_type' => $bookingsByType
        ]
    ];

    
    sendSuccess($response);
    
} catch (Exception $e) {
    logError('Daily Report Error', ['message' => $e->getMessage()]);
    sendError('Failed to generate daily report: ' . $e->getMessage(), 500);
}

