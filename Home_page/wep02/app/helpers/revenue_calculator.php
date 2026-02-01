<?php
/**
 * Revenue Calculator Helper
 * 
 * Implements STRICT CASH-BASED ACCOUNTING for accurate revenue tracking.
 * 
 * Core Principle:
 * Revenue = Money Actually Received (not billed amounts)
 * 
 * Formula:
 * Total Revenue = SUM(Initial Deposits) + SUM(Subsequent Payments)
 * 
 * Data Sources:
 * 1. invoices.deposit_amount - Initial deposits paid when invoice created
 * 2. payments table (type='payment') - All subsequent payments added via "Add Payment" modal
 * 
 * CRITICAL: This ensures that when a user adds a 100 SAR payment via "Order Actions",
 * it IMMEDIATELY appears in revenue calculations for dashboards and reports.
 */

class RevenueCalculator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate total revenue for a specific date (CASH-BASED)
     * 
     * @param string $date Date in Y-m-d format
     * @return float Total cash received on this date
     */
    public function calculateDailyRevenue($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        try {
            // Part 1: Initial deposits from invoices created on this date
            $depositsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits
                FROM invoices 
                WHERE DATE(invoice_date) = ?
                AND deleted_at IS NULL
            ");
            $depositsStmt->execute([$date]);
            $depositsResult = $depositsStmt->fetch(PDO::FETCH_ASSOC);
            $totalDeposits = floatval($depositsResult['total_deposits'] ?? 0);
            
            // Part 2: All payments added on this date (via "Add Payment" modal)
            // This is the CRITICAL FIX - includes subsequent payments
            $paymentsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_payments
                FROM payments 
                WHERE DATE(payment_date) = ?
                AND type = 'payment'
            ");
            $paymentsStmt->execute([$date]);
            $paymentsResult = $paymentsStmt->fetch(PDO::FETCH_ASSOC);
            $totalPayments = floatval($paymentsResult['total_payments'] ?? 0);
            
            // CRITICAL BUSINESS LOGIC:
            // We need to SUBTRACT any legacy deposits that were auto-migrated from invoices
            // to avoid double-counting. Legacy deposits are marked with notes containing "تم نقله تلقائياً"
            $legacyMigratedStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as legacy_migrated
                FROM payments 
                WHERE DATE(payment_date) = ?
                AND type = 'payment'
                AND notes LIKE '%تم نقله تلقائياً%'
            ");
            $legacyMigratedStmt->execute([$date]);
            $legacyResult = $legacyMigratedStmt->fetch(PDO::FETCH_ASSOC);
            $legacyMigrated = floatval($legacyResult['legacy_migrated'] ?? 0);
            
            // If legacy deposits were migrated on this date, they're already counted in totalDeposits
            // So we subtract them from totalPayments to avoid double-counting
            $actualNewPayments = $totalPayments - $legacyMigrated;
            
            // Total revenue = deposits + actual new payments
            $totalRevenue = $totalDeposits + $actualNewPayments;
            
            return $totalRevenue;
            
        } catch (PDOException $e) {
            // If payments table doesn't exist yet (old schema), fall back to deposits only
            error_log("RevenueCalculator: Payments table may not exist, using deposits only: " . $e->getMessage());
            
            try {
                $depositsStmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits
                    FROM invoices 
                    WHERE DATE(invoice_date) = ?
                    AND deleted_at IS NULL
                ");
                $depositsStmt->execute([$date]);
                $depositsResult = $depositsStmt->fetch(PDO::FETCH_ASSOC);
                return floatval($depositsResult['total_deposits'] ?? 0);
            } catch (PDOException $e2) {
                error_log("RevenueCalculator: Critical error - " . $e2->getMessage());
                return 0.0;
            }
        }
    }
    
    /**
     * Calculate total revenue for a date range (CASH-BASED)
     * 
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return float Total cash received in this period
     */
    public function calculateRevenueForPeriod($startDate, $endDate) {
        try {
            // Part 1: Initial deposits from invoices
            $depositsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits
                FROM invoices 
                WHERE DATE(invoice_date) BETWEEN ? AND ?
                AND deleted_at IS NULL
            ");
            $depositsStmt->execute([$startDate, $endDate]);
            $depositsResult = $depositsStmt->fetch(PDO::FETCH_ASSOC);
            $totalDeposits = floatval($depositsResult['total_deposits'] ?? 0);
            
            // Part 2: All payments in date range
            $paymentsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_payments
                FROM payments 
                WHERE DATE(payment_date) BETWEEN ? AND ?
                AND type = 'payment'
            ");
            $paymentsStmt->execute([$startDate, $endDate]);
            $paymentsResult = $paymentsStmt->fetch(PDO::FETCH_ASSOC);
            $totalPayments = floatval($paymentsResult['total_payments'] ?? 0);
            
            // Subtract auto-migrated legacy deposits to avoid double-counting
            $legacyMigratedStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as legacy_migrated
                FROM payments 
                WHERE DATE(payment_date) BETWEEN ? AND ?
                AND type = 'payment'
                AND notes LIKE '%تم نقله تلقائياً%'
            ");
            $legacyMigratedStmt->execute([$startDate, $endDate]);
            $legacyResult = $legacyMigratedStmt->fetch(PDO::FETCH_ASSOC);
            $legacyMigrated = floatval($legacyResult['legacy_migrated'] ?? 0);
            
            $actualNewPayments = $totalPayments - $legacyMigrated;
            
            return $totalDeposits + $actualNewPayments;
            
        } catch (PDOException $e) {
            error_log("RevenueCalculator: Error calculating period revenue - " . $e->getMessage());
            
            // Fallback to deposits only
            try {
                $depositsStmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits
                    FROM invoices 
                    WHERE DATE(invoice_date) BETWEEN ? AND ?
                    AND deleted_at IS NULL
                ");
                $depositsStmt->execute([$startDate, $endDate]);
                $depositsResult = $depositsStmt->fetch(PDO::FETCH_ASSOC);
                return floatval($depositsResult['total_deposits'] ?? 0);
            } catch (PDOException $e2) {
                error_log("RevenueCalculator: Critical error - " . $e2->getMessage());
                return 0.0;
            }
        }
    }
    
    /**
     * Get detailed revenue breakdown for a specific date
     * Useful for transparency and debugging
     * 
     * @param string $date Date in Y-m-d format
     * @return array Breakdown of deposits, payments, and total
     */
    public function getRevenueBreakdown($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        try {
            // Initial deposits
            $depositsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits,
                       COUNT(*) as invoice_count
                FROM invoices 
                WHERE DATE(invoice_date) = ?
                AND deleted_at IS NULL
            ");
            $depositsStmt->execute([$date]);
            $depositsData = $depositsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Subsequent payments (excluding auto-migrated legacy deposits)
            $paymentsStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_payments,
                       COUNT(*) as payment_count
                FROM payments 
                WHERE DATE(payment_date) = ?
                AND type = 'payment'
                AND (notes NOT LIKE '%تم نقله تلقائياً%' OR notes IS NULL)
            ");
            $paymentsStmt->execute([$date]);
            $paymentsData = $paymentsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Legacy deposits that were auto-migrated (for transparency)
            $legacyStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as legacy_amount,
                       COUNT(*) as legacy_count
                FROM payments 
                WHERE DATE(payment_date) = ?
                AND type = 'payment'
                AND notes LIKE '%تم نقله تلقائياً%'
            ");
            $legacyStmt->execute([$date]);
            $legacyData = $legacyStmt->fetch(PDO::FETCH_ASSOC);
            
            $totalDeposits = floatval($depositsData['total_deposits'] ?? 0);
            $totalPayments = floatval($paymentsData['total_payments'] ?? 0);
            $legacyAmount = floatval($legacyData['legacy_amount'] ?? 0);
            
            return [
                'date' => $date,
                'initial_deposits' => $totalDeposits,
                'initial_deposits_count' => intval($depositsData['invoice_count'] ?? 0),
                'subsequent_payments' => $totalPayments,
                'subsequent_payments_count' => intval($paymentsData['payment_count'] ?? 0),
                'legacy_migrated' => $legacyAmount,
                'legacy_migrated_count' => intval($legacyData['legacy_count'] ?? 0),
                'total_revenue' => $totalDeposits + $totalPayments,
                'calculation' => "{$totalDeposits} (deposits) + {$totalPayments} (payments) = " . ($totalDeposits + $totalPayments),
                'note' => 'Legacy migrated deposits are excluded from subsequent_payments to avoid double-counting'
            ];
            
        } catch (PDOException $e) {
            error_log("RevenueCalculator: Error getting breakdown - " . $e->getMessage());
            return [
                'date' => $date,
                'error' => $e->getMessage(),
                'total_revenue' => 0.0
            ];
        }
    }
    
    /**
     * Get all payment transactions for a specific date
     * Returns individual line items for Financial Ledger reports
     * 
     * @param string $date Date in Y-m-d format
     * @return array List of all payment transactions
     */
    public function getPaymentTransactions($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.payment_date,
                    p.amount,
                    p.method,
                    p.notes,
                    i.invoice_number,
                    c.name as customer_name
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE DATE(p.payment_date) = ?
                AND p.type = 'payment'
                ORDER BY p.payment_date ASC, p.id ASC
            ");
            $stmt->execute([$date]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $transactions;
            
        } catch (PDOException $e) {
            error_log("RevenueCalculator: Error fetching transactions - " . $e->getMessage());
            return [];
        }
    }
}
