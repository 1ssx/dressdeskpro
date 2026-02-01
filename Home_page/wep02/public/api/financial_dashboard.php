<?php
/**
 * Financial Dashboard API
 * Unified endpoint for comprehensive financial data
 * 
 * This API aggregates:
 * - Invoice payments (from payments table)
 * - Direct deposits (from invoices table - legacy)
 * - Expenses (from expenses table)
 * - Outstanding debts (unpaid balances)
 * 
 * Provides real-time, accurate financial reporting
 */

require_once __DIR__ . '/_common/bootstrap.php';

$action = getQueryParam('action', 'dashboard');

try {
    switch ($action) {
        
        /**
         * GET ?action=dashboard
         * Main dashboard summary with all financial metrics
         */
        case 'dashboard':
            // Get date range (defaults to today)
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Validate dates
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                sendError('Invalid date format. Use YYYY-MM-DD', 400);
            }
            
            // ===============================================
            // 1. TOTAL REVENUE CALCULATION
            // ===============================================
            
            // 1a. Get payments from payments table (order payments / partial payments)
            $paymentsTotal = 0;
            $paymentsByMethod = ['cash' => 0, 'card' => 0, 'transfer' => 0, 'mixed' => 0];
            $paymentsList = [];
            
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
                        p.created_at,
                        i.invoice_number,
                        c.name as customer_name
                    FROM payments p
                    LEFT JOIN invoices i ON p.invoice_id = i.id
                    LEFT JOIN customers c ON i.customer_id = c.id
                    WHERE DATE(p.payment_date) BETWEEN :date_from AND :date_to
                    AND p.type = 'payment'
                    ORDER BY p.payment_date DESC, p.id DESC
                ");
                $paymentsStmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
                $paymentsList = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($paymentsList as $payment) {
                    $amount = floatval($payment['amount']);
                    $paymentsTotal += $amount;
                    $method = $payment['method'] ?? 'cash';
                    $paymentsByMethod[$method] = ($paymentsByMethod[$method] ?? 0) + $amount;
                }
            } catch (PDOException $e) {
                // Payments table might not exist
                logError('Payments query failed', ['message' => $e->getMessage()]);
            }
            
            // 1b. Get deposits from invoices (for new invoices created in this period)
            $depositsTotal = 0;
            $invoicesInPeriod = [];
            
            try {
                $invoicesStmt = $pdo->prepare("
                    SELECT 
                        i.id,
                        i.invoice_number,
                        i.invoice_date,
                        i.total_price,
                        i.deposit_amount,
                        i.remaining_balance,
                        i.payment_status,
                        i.invoice_status,
                        i.payment_method,
                        i.operation_type,
                        c.name as customer_name,
                        c.phone_1 as customer_phone
                    FROM invoices i
                    LEFT JOIN customers c ON i.customer_id = c.id
                    WHERE DATE(i.created_at) BETWEEN :date_from AND :date_to
                    AND i.deleted_at IS NULL
                    ORDER BY i.invoice_date DESC, i.id DESC
                ");
                $invoicesStmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
                $invoicesInPeriod = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($invoicesInPeriod as $invoice) {
                    $depositsTotal += floatval($invoice['deposit_amount']);
                }
            } catch (PDOException $e) {
                logError('Invoices query failed', ['message' => $e->getMessage()]);
            }
            
            // Total Revenue = Payments + Deposits (from new invoices)
            $totalRevenue = $paymentsTotal + $depositsTotal;
            
            // ===============================================
            // 2. TOTAL EXPENSES
            // ===============================================
            $totalExpenses = 0;
            $expensesByCategory = [];
            $expensesList = [];
            
            try {
                $expensesStmt = $pdo->prepare("
                    SELECT 
                        id,
                        expense_date,
                        amount,
                        category,
                        description,
                        receipt_number
                    FROM expenses
                    WHERE DATE(expense_date) BETWEEN :date_from AND :date_to
                    ORDER BY expense_date DESC, id DESC
                ");
                $expensesStmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
                $expensesList = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($expensesList as $expense) {
                    $amount = floatval($expense['amount']);
                    $totalExpenses += $amount;
                    $category = $expense['category'] ?? 'أخرى';
                    $expensesByCategory[$category] = ($expensesByCategory[$category] ?? 0) + $amount;
                }
            } catch (PDOException $e) {
                logError('Expenses query failed', ['message' => $e->getMessage()]);
            }
            
            // ===============================================
            // 3. NET PROFIT
            // ===============================================
            $netProfit = $totalRevenue - $totalExpenses;
            
            // ===============================================
            // 4. OUTSTANDING DEBTS (Remaining Balances)
            // ===============================================
            $outstandingDebts = 0;
            $unpaidInvoices = [];
            
            try {
                $unpaidStmt = $pdo->prepare("
                    SELECT 
                        i.id,
                        i.invoice_number,
                        i.invoice_date,
                        i.total_price,
                        i.remaining_balance,
                        i.payment_status,
                        c.name as customer_name,
                        c.phone_1 as customer_phone
                    FROM invoices i
                    LEFT JOIN customers c ON i.customer_id = c.id
                    WHERE i.remaining_balance > 0
                    AND i.payment_status != 'paid'
                    AND i.invoice_status NOT IN ('canceled', 'draft')
                    AND i.deleted_at IS NULL
                    ORDER BY i.remaining_balance DESC
                    LIMIT 20
                ");
                $unpaidStmt->execute();
                $unpaidInvoices = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total outstanding
                $totalUnpaidStmt = $pdo->query("
                    SELECT COALESCE(SUM(remaining_balance), 0) as total
                    FROM invoices 
                    WHERE remaining_balance > 0 
                    AND payment_status != 'paid'
                    AND invoice_status NOT IN ('canceled', 'draft')
                    AND deleted_at IS NULL
                ");
                $outstandingDebts = floatval($totalUnpaidStmt->fetchColumn());
            } catch (PDOException $e) {
                logError('Outstanding debts query failed', ['message' => $e->getMessage()]);
            }
            
            // ===============================================
            // 5. DAILY BREAKDOWN (for charts)
            // ===============================================
            $dailyData = [];
            
            try {
                // Revenue by day
                $dailyRevenueStmt = $pdo->prepare("
                    SELECT 
                        DATE(payment_date) as day,
                        SUM(amount) as total
                    FROM payments
                    WHERE DATE(payment_date) BETWEEN :date_from AND :date_to
                    AND type = 'payment'
                    GROUP BY DATE(payment_date)
                    ORDER BY day ASC
                ");
                $dailyRevenueStmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
                $dailyRevenue = $dailyRevenueStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Expenses by day
                $dailyExpensesStmt = $pdo->prepare("
                    SELECT 
                        DATE(expense_date) as day,
                        SUM(amount) as total
                    FROM expenses
                    WHERE DATE(expense_date) BETWEEN :date_from AND :date_to
                    GROUP BY DATE(expense_date)
                    ORDER BY day ASC
                ");
                $dailyExpensesStmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
                $dailyExpenses = $dailyExpensesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Merge into daily data
                $revByDay = [];
                $expByDay = [];
                foreach ($dailyRevenue as $row) {
                    $revByDay[$row['day']] = floatval($row['total']);
                }
                foreach ($dailyExpenses as $row) {
                    $expByDay[$row['day']] = floatval($row['total']);
                }
                
                // Generate all dates in range
                $start = new DateTime($dateFrom);
                $end = new DateTime($dateTo);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                
                foreach ($period as $date) {
                    $day = $date->format('Y-m-d');
                    $dailyData[] = [
                        'date' => $day,
                        'revenue' => $revByDay[$day] ?? 0,
                        'expenses' => $expByDay[$day] ?? 0,
                        'profit' => ($revByDay[$day] ?? 0) - ($expByDay[$day] ?? 0)
                    ];
                }
            } catch (Exception $e) {
                logError('Daily breakdown query failed', ['message' => $e->getMessage()]);
            }
            
            // ===============================================
            // 6. COMBINED TRANSACTION LOG
            // ===============================================
            $transactionLog = [];
            
            // Add payments as income
            foreach ($paymentsList as $payment) {
                $transactionLog[] = [
                    'type' => 'income',
                    'source' => 'payment',
                    'date' => $payment['payment_date'],
                    'amount' => floatval($payment['amount']),
                    'description' => 'دفعة للفاتورة ' . ($payment['invoice_number'] ?? '#' . $payment['invoice_id']),
                    'customer' => $payment['customer_name'] ?? null,
                    'method' => $payment['method'],
                    'reference' => $payment['invoice_number']
                ];
            }
            
            // Add expenses as outgo
            foreach ($expensesList as $expense) {
                $transactionLog[] = [
                    'type' => 'expense',
                    'source' => 'expense',
                    'date' => $expense['expense_date'],
                    'amount' => floatval($expense['amount']),
                    'description' => $expense['description'] ?? $expense['category'],
                    'category' => $expense['category'],
                    'reference' => $expense['receipt_number']
                ];
            }
            
            // Sort by date descending
            usort($transactionLog, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            // ===============================================
            // BUILD RESPONSE
            // ===============================================
            $response = [
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                
                // Main KPIs
                'summary' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_profit' => round($netProfit, 2),
                    'outstanding_debts' => round($outstandingDebts, 2),
                    
                    // Breakdown
                    'payments_received' => round($paymentsTotal, 2),
                    'deposits_received' => round($depositsTotal, 2),
                    
                    // Counts
                    'payments_count' => count($paymentsList),
                    'invoices_count' => count($invoicesInPeriod),
                    'expenses_count' => count($expensesList)
                ],
                
                // Revenue by payment method
                'revenue_by_method' => $paymentsByMethod,
                
                // Expenses by category
                'expenses_by_category' => $expensesByCategory,
                
                // Daily breakdown for charts
                'daily_breakdown' => $dailyData,
                
                // Transaction log (combined income + expenses)
                'transactions' => array_slice($transactionLog, 0, 100), // Limit to 100
                
                // Top unpaid invoices
                'unpaid_invoices' => $unpaidInvoices,
                
                // Raw data for tables
                'payments' => $paymentsList,
                'expenses' => $expensesList,
                'invoices' => $invoicesInPeriod
            ];
            
            sendSuccess($response);
            break;
            
        /**
         * GET ?action=quick_stats
         * Quick stats for today (for header cards)
         */
        case 'quick_stats':
            $today = date('Y-m-d');
            
            // Today's payments
            $todayPayments = 0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = ? AND type = 'payment'");
                $stmt->execute([$today]);
                $todayPayments = floatval($stmt->fetchColumn());
            } catch (PDOException $e) {}
            
            // Today's new invoice deposits
            $todayDeposits = 0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(deposit_amount), 0) FROM invoices WHERE DATE(created_at) = ? AND deleted_at IS NULL");
                $stmt->execute([$today]);
                $todayDeposits = floatval($stmt->fetchColumn());
            } catch (PDOException $e) {}
            
            // Today's expenses
            $todayExpenses = 0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(expense_date) = ?");
                $stmt->execute([$today]);
                $todayExpenses = floatval($stmt->fetchColumn());
            } catch (PDOException $e) {}
            
            // Total outstanding
            $outstanding = 0;
            try {
                $stmt = $pdo->query("SELECT COALESCE(SUM(remaining_balance), 0) FROM invoices WHERE remaining_balance > 0 AND payment_status != 'paid' AND invoice_status NOT IN ('canceled', 'draft') AND deleted_at IS NULL");
                $outstanding = floatval($stmt->fetchColumn());
            } catch (PDOException $e) {}
            
            $todayRevenue = $todayPayments + $todayDeposits;
            $todayProfit = $todayRevenue - $todayExpenses;
            
            sendSuccess([
                'today_revenue' => round($todayRevenue, 2),
                'today_expenses' => round($todayExpenses, 2),
                'today_profit' => round($todayProfit, 2),
                'outstanding_debts' => round($outstanding, 2),
                
                // Breakdown
                'today_payments' => round($todayPayments, 2),
                'today_deposits' => round($todayDeposits, 2)
            ]);
            break;
            
        default:
            sendError('Unknown action', 400);
    }
    
} catch (Throwable $e) {
    logError('Financial Dashboard API Error', [
        'message' => $e->getMessage(),
        'action' => $action,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    sendError('Server error: ' . $e->getMessage(), 500);
}
