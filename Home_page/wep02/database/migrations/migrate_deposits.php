<?php
/**
 * Migration: Convert Legacy Deposits to Payments Table
 * 
 * هذا السكربت يقوم بتحويل العربونات القديمة (deposit_amount) من جدول الفواتير
 * إلى سجلات في جدول payments الجديد
 * 
 * يجب تشغيل هذا السكربت مرة واحدة فقط لكل متجر
 * 
 * Usage: php migrate_deposits.php [store_id]
 */

// تشغيل من سطر الأوامر فقط
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

// تحميل إعدادات قاعدة البيانات
$baseDir = realpath(__DIR__ . '/../../');

$candidatePaths = [
    $baseDir . '/config/database.php',
    $baseDir . '/app/config/database.php',
    $baseDir . '/app/config/config.php',
];

$dbConfigLoaded = false;
foreach ($candidatePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbConfigLoaded = true;
        break;
    }
}

if (!$dbConfigLoaded) {
    die("Could not locate database config file. Tried:\n" . implode("\n", $candidatePaths));
}
// تحديد متجر معين أو الكل
$storeId = isset($argv[1]) ? intval($argv[1]) : null;

echo "=== Migration: Legacy Deposits to Payments ===\n\n";

try {
    // الحصول على قائمة قواعد البيانات
    if ($storeId) {
        $stores = [['id' => $storeId, 'database_name' => 'store_' . $storeId]];
    } else {
        // إذا لا يوجد متجر محدد، نفترض أننا نعمل على المتجر الحالي
        echo "No store ID specified. Processing current database...\n\n";
        $stores = [['id' => 0, 'database_name' => 'current']];
    }
    
    $totalMigrated = 0;
    $totalSkipped = 0;
    $totalErrors = 0;
    
    foreach ($stores as $store) {
        $storeLabel = $store['database_name'];
        echo "Processing: {$storeLabel}\n";
        echo str_repeat('-', 50) . "\n";
        
        // استخدام الاتصال الحالي
        global $pdo;
        
        if (!isset($pdo)) {
            echo "ERROR: PDO connection not available\n\n";
            continue;
        }
        
        // البحث عن الفواتير التي لديها عربون ولا توجد لها دفعات
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                i.deposit_amount,
                i.invoice_date,
                i.created_by
            FROM invoices i
            WHERE i.deposit_amount > 0
              AND i.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM payments p 
                  WHERE p.invoice_id = i.id 
                    AND p.type = 'payment'
              )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = count($invoices);
        echo "Found {$count} invoices with legacy deposits to migrate\n\n";
        
        if ($count == 0) {
            echo "No migration needed.\n\n";
            continue;
        }
        
        // بدء المعاملة
        $pdo->beginTransaction();
        
        try {
            foreach ($invoices as $invoice) {
                $invoiceId = $invoice['invoice_id'];
                $invoiceNumber = $invoice['invoice_number'];
                $depositAmount = floatval($invoice['deposit_amount']);
                $invoiceDate = $invoice['invoice_date'];
                $createdBy = $invoice['created_by'];
                
                echo "  - Invoice #{$invoiceNumber} (ID: {$invoiceId}): ";
                
                // إضافة الدفعة في جدول payments
                $insertSql = "
                    INSERT INTO payments (
                        invoice_id, 
                        payment_date, 
                        amount, 
                        method, 
                        type, 
                        notes, 
                        created_by,
                        created_at
                    ) VALUES (?, ?, ?, 'cash', 'payment', ?, ?, NOW())
                ";
                
                $insertStmt = $pdo->prepare($insertSql);
                $success = $insertStmt->execute([
                    $invoiceId,
                    $invoiceDate ?: date('Y-m-d H:i:s'),
                    $depositAmount,
                    'عربون مهاجَر من النظام القديم',
                    $createdBy
                ]);
                
                if ($success) {
                    echo "MIGRATED ({$depositAmount} SAR)\n";
                    $totalMigrated++;
                } else {
                    echo "FAILED\n";
                    $totalErrors++;
                }
            }
            
            // تأكيد المعاملة
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "ERROR: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
        
        echo "\n";
    }
    
    echo "=== Migration Summary ===\n";
    echo "Migrated: {$totalMigrated}\n";
    echo "Skipped:  {$totalSkipped}\n";
    echo "Errors:   {$totalErrors}\n";
    echo "\n";
    
    if ($totalMigrated > 0) {
        echo "Migration completed successfully!\n";
        echo "Note: Run this migration for each store database if running multi-tenant.\n";
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
