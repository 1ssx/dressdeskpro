<?php
/**
 * Receivables API
 * 
 * تقارير الذمم والديون
 * 
 * Actions:
 * - list: قائمة الفواتير غير المسددة
 * - aging: تقرير الأعمار (30, 60, 90+ يوم)
 * - by_customer: رصيد كل عميل
 */

// Load common infrastructure
require_once __DIR__ . '/_common/bootstrap.php';

// Load required helpers
require_once __DIR__ . '/../../app/helpers/payment_manager.php';

$action = $_GET['action'] ?? 'list';

try {
    $paymentManager = new PaymentManager($pdo);
    
    switch ($action) {
        case 'list':
            handleList($pdo, $paymentManager);
            break;
            
        case 'aging':
            handleAging($pdo);
            break;
            
        case 'by_customer':
            handleByCustomer($pdo, $paymentManager);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * قائمة الفواتير غير المسددة بالكامل
 */
function handleList($pdo, $paymentManager) {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $customerId = $_GET['customer_id'] ?? null;
    $minAmount = floatval($_GET['min_amount'] ?? 0);
    
    try {
        $sql = "
            SELECT 
                i.id,
                i.invoice_number,
                i.invoice_date,
                i.invoice_status,
                i.payment_status,
                i.operation_type,
                i.total_price,
                i.wedding_date,
                c.name as customer_name,
                c.phone_1 as customer_phone,
                c.id as customer_id,
                DATEDIFF(CURDATE(), i.invoice_date) as days_overdue
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.deleted_at IS NULL
              AND i.invoice_status NOT IN ('closed', 'canceled')
              AND i.payment_status != 'paid'
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND DATE(i.invoice_date) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(i.invoice_date) <= ?";
            $params[] = $endDate;
        }
        
        if ($customerId) {
            $sql .= " AND i.customer_id = ?";
            $params[] = $customerId;
        }
        
        $sql .= " ORDER BY i.invoice_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // حساب المبلغ المتبقي لكل فاتورة
        $result = [];
        $totalRemaining = 0;
        
        foreach ($invoices as $invoice) {
            $remaining = $paymentManager->calculateRemainingBalance($invoice['id']);
            
            if ($remaining > $minAmount) {
                $invoice['remaining_balance'] = $remaining;
                $invoice['total_paid'] = $paymentManager->calculateTotalPaid($invoice['id']);
                
                // تحديد فئة التأخير
                $daysOverdue = intval($invoice['days_overdue']);
                if ($daysOverdue <= 30) {
                    $invoice['aging_bucket'] = '0-30';
                } elseif ($daysOverdue <= 60) {
                    $invoice['aging_bucket'] = '31-60';
                } elseif ($daysOverdue <= 90) {
                    $invoice['aging_bucket'] = '61-90';
                } else {
                    $invoice['aging_bucket'] = '90+';
                }
                
                $result[] = $invoice;
                $totalRemaining += $remaining;
            }
        }
        
        sendSuccess([
            'invoices' => $result,
            'count' => count($result),
            'total_remaining' => $totalRemaining,
            'summary' => [
                'total_invoices' => count($result),
                'total_amount_due' => $totalRemaining
            ]
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * تقرير الأعمار (Aging Report)
 */
function handleAging($pdo) {
    try {
        $sql = "
            SELECT 
                CASE 
                    WHEN DATEDIFF(CURDATE(), invoice_date) <= 30 THEN '0-30'
                    WHEN DATEDIFF(CURDATE(), invoice_date) <= 60 THEN '31-60'
                    WHEN DATEDIFF(CURDATE(), invoice_date) <= 90 THEN '61-90'
                    ELSE '90+'
                END as aging_bucket,
                COUNT(*) as invoice_count,
                SUM(remaining_balance) as total_amount
            FROM invoices
            WHERE deleted_at IS NULL
              AND invoice_status NOT IN ('closed', 'canceled')
              AND payment_status != 'paid'
              AND remaining_balance > 0
            GROUP BY aging_bucket
            ORDER BY 
                CASE aging_bucket
                    WHEN '0-30' THEN 1
                    WHEN '31-60' THEN 2
                    WHEN '61-90' THEN 3
                    WHEN '90+' THEN 4
                END
        ";
        
        $stmt = $pdo->query($sql);
        $agingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // حساب الإجماليات
        $totalInvoices = 0;
        $totalAmount = 0;
        
        foreach ($agingData as $row) {
            $totalInvoices += intval($row['invoice_count']);
            $totalAmount += floatval($row['total_amount']);
        }
        
        // حساب النسب المئوية
        foreach ($agingData as &$row) {
            $row['percentage'] = $totalAmount > 0 
                ? round((floatval($row['total_amount']) / $totalAmount) * 100, 2)
                : 0;
        }
        
        sendSuccess([
            'aging_buckets' => $agingData,
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * رصيد كل عميل (تجميع حسب العميل)
 */
function handleByCustomer($pdo, $paymentManager) {
    $minAmount = floatval($_GET['min_amount'] ?? 0);
    
    try {
        $sql = "
            SELECT 
                c.id as customer_id,
                c.name as customer_name,
                c.phone_1,
                c.phone_2,
                c.type as customer_type,
                COUNT(i.id) as invoice_count,
                SUM(i.total_price) as total_invoiced
            FROM customers c
            INNER JOIN invoices i ON c.id = i.customer_id
            WHERE i.deleted_at IS NULL
              AND i.invoice_status NOT IN ('closed', 'canceled')
              AND i.payment_status != 'paid'
            GROUP BY c.id
            ORDER BY total_invoiced DESC
        ";
        
        $stmt = $pdo->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        $grandTotal = 0;
        
        foreach ($customers as $customer) {
            $customerId = $customer['customer_id'];
            
            // جلب جميع فواتير هذا العميل غير المسددة
            $stmt = $pdo->prepare("
                SELECT id FROM invoices 
                WHERE customer_id = ? 
                  AND deleted_at IS NULL
                  AND invoice_status NOT IN ('closed', 'canceled')
                  AND payment_status != 'paid'
            ");
            $stmt->execute([$customerId]);
            $invoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // حساب إجمالي المتبقي لهذا العميل
            $totalRemaining = 0;
            $totalPaid = 0;
            
            foreach ($invoiceIds as $invoiceId) {
                $remaining = $paymentManager->calculateRemainingBalance($invoiceId);
                $paid = $paymentManager->calculateTotalPaid($invoiceId);
                
                $totalRemaining += $remaining;
                $totalPaid += $paid;
            }
            
            if ($totalRemaining > $minAmount) {
                $customer['total_remaining'] = $totalRemaining;
                $customer['total_paid'] = $totalPaid;
                $customer['invoices'] = $invoiceIds;
                
                $result[] = $customer;
                $grandTotal += $totalRemaining;
            }
        }
        
        sendSuccess([
            'customers' => $result,
            'count' => count($result),
            'grand_total' => $grandTotal
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}
