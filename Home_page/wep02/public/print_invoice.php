<?php
/**
 * Print Invoice Page
 * صفحة طباعة الفاتورة الاحترافية
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/print_invoice_generator.php';
require_once __DIR__ . '/../app/helpers/payment_manager.php';

// Get invoice ID from URL
$invoiceId = $_GET['invoice_id'] ?? $_GET['id'] ?? null;

if (!$invoiceId) {
    echo '<html><body style="font-family: sans-serif; text-align: center; padding: 50px;">';
    echo '<h1>خطأ</h1>';
    echo '<p>لم يتم تحديد رقم الفاتورة</p>';
    echo '<a href="sales.php">العودة للفواتير</a>';
    echo '</body></html>';
    exit;
}

try {
    // استخدام PaymentManager للحصول على المدفوعات الفعلية
    $paymentManager = new PaymentManager($pdo);
    $totalPaid = $paymentManager->calculateTotalPaid($invoiceId);
    $remaining = $paymentManager->calculateRemainingBalance($invoiceId);
    
    // Generate the print HTML - the function uses $invoice['deposit_amount'] and $invoice['remaining_balance']
    // So we need to update the invoice data first
    $stmt = $pdo->prepare("
        UPDATE invoices SET 
            deposit_amount = ?,
            remaining_balance = ?
        WHERE id = ?
    ");
    $stmt->execute([$totalPaid, $remaining, $invoiceId]);
    
    // Now generate the HTML with updated values
    $html = generatePrintInvoiceHTML($invoiceId, $pdo);
    
    if (!$html) {
        throw new Exception('الفاتورة غير موجودة');
    }
    
    // Output the HTML
    echo $html;
    
} catch (Exception $e) {
    echo '<html><body style="font-family: sans-serif; text-align: center; padding: 50px;">';
    echo '<h1>خطأ</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="sales.php">العودة للفواتير</a>';
    echo '</body></html>';
}

