<?php
// save_invoice.php - محدث بنظام الربط التلقائي مع العملاء + WhatsApp Integration
ob_start();

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Start session for store context
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Connect to store database
    if (!isset($_SESSION['store_db_name'])) {
        throw new Exception("Store context not found. Please log in again.");
    }
    
    $pdo = require __DIR__ . '/../app/config/store_database.php';
    
    // Load CustomerHelper
    $helperPath = __DIR__ . '/CustomerHelper.php';
    if (!file_exists($helperPath)) {
        throw new Exception("ملف CustomerHelper غير موجود");
    }
    require_once $helperPath;
    
    // Load WhatsApp Helper
    $whatsappHelperPath = __DIR__ . '/../app/helpers/whatsapp.php';
    if (file_exists($whatsappHelperPath)) {
        require_once $whatsappHelperPath;
    }
    
    // Load Invoice Helpers (for auto-creating products)
    $invoiceHelperPath = __DIR__ . '/../app/helpers/invoice_helpers.php';
    if (file_exists($invoiceHelperPath)) {
        require_once $invoiceHelperPath;
    }
    
    ob_clean();

    if (!isset($pdo)) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }

    // قبول POST فقط
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("طريقة الطلب غير صحيحة");
    }

    // الحصول على البيانات
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("بيانات غير صحيحة");
    }

    // التحقق من الحقول المطلوبة
    $required = ['customer_name', 'phone_1', 'total_price', 'operation_type', 'payment_method', 'invoice_date'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("الحقل {$field} مطلوب");
        }
    }

    $isEdit = !empty($data['id']);
    $invoiceId = $isEdit ? intval($data['id']) : null;

    // Start transaction
    $pdo->beginTransaction();

    // إنشاء مساعد العملاء
    $customerHelper = new CustomerHelper($pdo);
    
    // البحث عن العميل أو إنشاؤه تلقائياً
    $customerData = [
        
        'name' => $data['customer_name'],
        'phone_1' => $data['phone_1'],
        'phone_2' => $data['phone_2'] ?? null,
        'address' => null,
        'notes' => null
    ];
    
    error_log("Processing customer: " . print_r($customerData, true));
    
    try {
        $customer = $customerHelper->createOrGetCustomer($customerData);
        $customerId = $customer['id'];
    } catch (Exception $e) {
        throw $e;
    }

    if ($isEdit) {
        
        // تحديث فاتورة موجودة (V2 Schema - Normalized)
        
        // حساب حالة الدفع
        $totalPrice = floatval($data['total_price']);
        $depositAmount = floatval($data['deposit_amount'] ?? 0);
        $remainingBalance = $totalPrice - $depositAmount;
        
        $paymentStatus = 'unpaid';
        if ($remainingBalance <= 0.01) {
            $paymentStatus = 'paid';
        } elseif ($depositAmount > 0) {
            $paymentStatus = 'partial';
        }
        
        $stmt = $pdo->prepare("
            UPDATE invoices SET
                invoice_date = ?,
                operation_type = ?,
                payment_method = ?,
                total_price = ?,
                deposit_amount = ?,
                remaining_balance = ?,
                payment_status = ?,
                customer_id = ?,
                wedding_date = ?,
                collection_date = ?,
                return_date = ?,
                notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['invoice_date'],
            $data['operation_type'],
            $data['payment_method'],
            $totalPrice,
            $depositAmount,
            $remainingBalance,
            $paymentStatus,
            $customerId,
            $data['wedding_date'] ?? null,
        // حساب حالة الدفع
        $totalPrice = floatval($data['total_price']);
        $depositAmount = floatval($data['deposit_amount'] ?? 0);
        $remainingBalance = $totalPrice - $depositAmount;
        
        $paymentStatus = 'unpaid';
        if ($remainingBalance <= 0.01) {
            $paymentStatus = 'paid';
        } elseif ($depositAmount > 0) {
            $paymentStatus = 'partial';
        }

        // Enforce business rule: return_date must be NULL for sale operations
        $operationType = $data['operation_type'] ?? '';
        $returnDate = null;
        
        // Only set return_date if operation is NOT a sale
        if (!in_array($operationType, ['sale', 'design-sale'])) {
            $returnDate = !empty($data['return_date']) ? $data['return_date'] : null;
        }
        
        // Get current user ID for created_by field
        $createdBy = $_SESSION['user_id'] ?? null;
        
        // إدراج الفاتورة (Schema includes created_by field)
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                invoice_number, invoice_date, operation_type, payment_method,
                total_price, deposit_amount, remaining_balance, payment_status,
                customer_id, wedding_date, collection_date, return_date, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $invoiceNumber,
                $data['invoice_date'],
                $operationType,
                $data['payment_method'],
                $totalPrice,
                $depositAmount,
                $remainingBalance,
                $paymentStatus,
                $customerId,
                $data['wedding_date'] ?? null,
                $data['collection_date'] ?? null,
                $returnDate, // Use the validated return_date (NULL for sales)
                $data['notes'] ?? null,
                $createdBy
            ]);
            $invoiceId = $pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to insert invoice: " . $e->getMessage());
            throw $e;
        }

        // إدراج العناصر (V2 Schema)
        insertInvoiceItemV2($pdo, $invoiceId, $data);
        insertAccessoriesAsItems($pdo, $invoiceId, $data['accessories'] ?? []);
        error_log("Items inserted for new invoice");

        $message = "تم حفظ الفاتورة بنجاح";
        
        // Send WhatsApp message with PDF after successful invoice creation
        if (function_exists('sendWhatsAppWithFile') && function_exists('formatInvoiceMessage') && function_exists('generateInvoicePDFUrl')) {
            // Get customer phone number
            $customerPhone = $customer['phone_1'] ?? $data['phone_1'] ?? null;
            
            if (!empty($customerPhone)) {
                $customerName = $customer['name'] ?? $data['customer_name'];
                $whatsappMessage = formatInvoiceMessage($customerName, $invoiceNumber, 'created');
                
                // Send WhatsApp with PDF (non-blocking - don't fail if it doesn't work)
                try {
                    // Generate PDF URL
                    $pdfUrl = generateInvoicePDFUrl($invoiceId, $pdo);
                    
                    if ($pdfUrl) {
                        // Send with PDF attachment
                        $whatsappResult = sendWhatsAppWithFile(
                            $customerPhone,
                            $whatsappMessage,
                            $pdfUrl,
                            'invoice_' . $invoiceNumber . '.html'
                        );
                    } else {
                        // Fallback to text-only if PDF generation fails
                        if (function_exists('sendWhatsApp')) {
                            $whatsappResult = sendWhatsApp($customerPhone, $whatsappMessage);
                        }
                    }
                    // Optionally log the result but don't block invoice creation
                } catch (Exception $e) {
                    // Silently fail - WhatsApp is not critical
                    error_log("WhatsApp sending error: " . $e->getMessage());
                }
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    ob_clean();
    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => $message,
        'invoice_id' => $invoiceId,
        'invoice_number' => $invoiceNumber ?? null,
        'customer_id' => $customerId,
        'customer' => $customer
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log("CRITICAL ERROR in save_invoice.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction rolled back due to error");
    }
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

function generateInvoiceNumber($pdo) {
    try {
        // Get the last invoice number from the current store's database
        $stmt = $pdo->prepare("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = 1; // Default to 1 for first invoice
        
        if ($lastInvoice && !empty($lastInvoice['invoice_number'])) {
            $lastNumber = $lastInvoice['invoice_number'];
            // Extract number from format INV-0001
            if (preg_match('/INV-(\d+)/', $lastNumber, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            }
        }
        
        // Format with leading zeros (e.g., INV-0001, INV-0002, etc.)
        $invoiceNumber = 'INV-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // Double-check this number doesn't already exist (race condition protection)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE invoice_number = ?");
        $checkStmt->execute([$invoiceNumber]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // If it exists, increment until we find a unique one
        while ($exists && $exists['count'] > 0) {
            $nextNum++;
            $invoiceNumber = 'INV-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            $checkStmt->execute([$invoiceNumber]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $invoiceNumber;
    } catch (Exception $e) {
        // If anything fails, generate a timestamp-based number as fallback
        return 'INV-' . date('YmdHis');
    }
}

// V2 Schema: Store dress data in invoice_items with measurements as JSON
// + Auto-create product in inventory and lock it
function insertInvoiceItemV2($pdo, $invoiceId, $data) {
    // بناء JSON للقياسات
    $measurements = json_encode([
        'bust' => $data['bust_meas'] ?? null,
        'waist' => $data['waist_meas'] ?? null,
        'length' => $data['dress_len'] ?? null,
        'sleeve' => $data['sleeve_len'] ?? null,
        'shoulder' => $data['shoulder_len'] ?? null,
        'other' => $data['other_meas'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    
    $totalPrice = floatval($data['total_price'] ?? 0);
    
    // Schema: invoice_items HAS item_code, color, size columns
    $itemName = $data['dress_name'] ?? 'فستان';
    $itemCode = $data['dress_code'] ?? null;
    $color = $data['dress_color'] ?? null;
    $size = $data['dress_size'] ?? null;
    
    // ✨ AUTO-CREATE PRODUCT IN INVENTORY ✨
    $productId = null;
    if (function_exists('autoCreateProduct')) {
        try {
            error_log("Attempting to auto-create product: $itemName");
            $itemData = [
                'item_name' => $itemName,
                'item_code' => $itemCode,
                'color' => $color,
                'size' => $size,
                'unit_price' => $totalPrice,
                'category_char' => $data['dress_category'] ?? null
            ];
            $productId = autoCreateProduct($pdo, $itemData);
            error_log("Product auto-created/found with ID: $productId");
        } catch (Exception $e) {
            // Log error but don't fail invoice creation
            error_log('Auto-create product error: ' . $e->getMessage());
        }
    } else {
        error_log("WARNING: autoCreateProduct function does not exist!");
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO invoice_items (
            invoice_id, product_id, item_type, item_name, item_code, color, size,
            quantity, unit_price, total_price, measurements, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $invoiceId,
        $productId,  // Link to auto-created product
        'custom_dress',
        $itemName,
        $itemCode,
        $color,
        $size,
        1,
        $totalPrice,
        $totalPrice,
        $measurements,
        null
    ]);
}

// V2 Schema: Store accessories as invoice_items with item_type='accessory'
function insertAccessoriesAsItems($pdo, $invoiceId, $accessories) {
    if (!is_array($accessories) || empty($accessories)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO invoice_items (
            invoice_id, item_type, item_name, quantity, unit_price, total_price
        ) VALUES (?, 'accessory', ?, 1, 0, 0)
    ");

    foreach ($accessories as $item) {
        if (!empty($item)) {
            $stmt->execute([$invoiceId, $item]);
        }
    }
}

// autoCreateProduct function has been moved to app/helpers/invoice_helpers.php
// DO NOT REDECLARE HERE - it causes PHP fatal error "Cannot redeclare autoCreateProduct()"