<?php
/**
 * Invoices API - Enhanced Version
 * 
 * إدارة شاملة للفواتير مع دعم دورة الحياة الكاملة
 * 
 * Actions:
 * - list: قائمة الفواتير
 * - details: تفاصيل فاتورة واحدة
 * - get_invoice: جلب فاتورة (backward compatibility)
 * - stats: إحصائيات
 * - generate_number: توليد رقم فاتورة
 * - change_status: تغيير حالة الفاتورة
 * - deliver: تأكيد تسليم الفستان
 * - return: تأكيد إرجاع الفستان
 * - close: إقفال الفاتورة
 * - cancel: إلغاء الفاتورة (soft delete)
 * - archive_list: قائمة الأرشيف
 * - send_invoice_message: إرسال WhatsApp
 */

// Load common infrastructure
require_once __DIR__ . '/_common/bootstrap.php';

// Load required helpers
require_once __DIR__ . '/../../app/helpers/invoice_lifecycle.php';
require_once __DIR__ . '/../../app/helpers/payment_manager.php';
require_once __DIR__ . '/../../app/helpers/permissions.php';
require_once __DIR__ . '/../../app/helpers/logger.php';

$action = $_GET['action'] ?? 'list';

try {
    // Initialize helpers
    $lifecycleManager = new InvoiceLifecycleManager($pdo);
    $paymentManager = new PaymentManager($pdo);
    $permissions = new PermissionsManager($pdo, $_SESSION['user_id'] ?? null);
    $logger = new StoreLogger($pdo);
    $userId = $_SESSION['user_id'] ?? null;
    
    switch ($action) {
        case 'list':
            handleList($pdo, $paymentManager);
            break;
            
        case 'details':
        case 'get_invoice':
            handleDetails($pdo, $lifecycleManager, $paymentManager);
            break;
            
        case 'stats':
            handleStats($pdo);
            break;
            
        case 'generate_number':
            handleGenerateNumber($pdo);
            break;
            
        case 'change_status':
            handleChangeStatus($pdo, $lifecycleManager, $permissions, $logger, $userId);
            break;
            
        case 'deliver':
            handleDeliver($pdo, $lifecycleManager, $permissions, $logger, $userId);
            break;
            
        case 'return':
            handleReturn($pdo, $lifecycleManager, $permissions, $logger, $userId);
            break;
            
        case 'close':
            handleClose($pdo, $lifecycleManager, $permissions, $logger, $userId);
            break;
            
        case 'cancel':
        case 'delete':
            handleCancel($pdo, $lifecycleManager, $permissions, $logger, $userId);
            break;
            
        case 'archive_list':
            handleArchiveList($pdo, $paymentManager);
            break;
            
        case 'send_invoice_message':
            handleSendInvoiceMessage($pdo);
            break;
            
        case 'check_availability':
            handleCheckAvailability($pdo);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    logError('Invoices API Error', ['action' => $action, 'message' => $e->getMessage()]);
    sendError($e->getMessage(), 500);
}

/**
 * قائمة الفواتير مع فلاتر محسّنة
 */
function handleList($pdo, $paymentManager) {
    try {
        $search = $_GET['search'] ?? '';
        $paymentStatusFilter = $_GET['payment_status'] ?? '';
        $invoiceStatusFilter = $_GET['invoice_status'] ?? '';
        $operationType = $_GET['operation_type'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $sql = "
            SELECT 
                i.id,
                i.invoice_number,
                i.invoice_date,
                i.payment_status,
                i.invoice_status,
                i.operation_type,
                i.total_price,
                i.deposit_amount,
                i.remaining_balance,
                i.wedding_date,
                i.collection_date,
                i.return_date,
                i.delivered_at,
                i.returned_at,
                c.name as customer_name,
                c.phone_1 as customer_phone,
                c.id as customer_id
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.deleted_at IS NULL
        ";
        
        $params = [];
        
        // Search filter
        if (!empty($search)) {
            $sql .= " AND (i.invoice_number LIKE ? OR c.name LIKE ? OR c.phone_1 LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Payment status filter
        if (!empty($paymentStatusFilter)) {
            $sql .= " AND i.payment_status = ?";
            $params[] = $paymentStatusFilter;
        }
        
        // Invoice status filter (NEW)
        if (!empty($invoiceStatusFilter)) {
            $sql .= " AND i.invoice_status = ?";
            $params[] = $invoiceStatusFilter;
        }
        
        // Operation type filter
        if (!empty($operationType)) {
            $sql .= " AND i.operation_type = ?";
            $params[] = $operationType;
        }
        
        // Date range filters
        if (!empty($startDate)) {
            $sql .= " AND DATE(i.invoice_date) >= ?";
            $params[] = $startDate;
        }
        
        if (!empty($endDate)) {
            $sql .= " AND DATE(i.invoice_date) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY i.invoice_date DESC, i.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get item counts for all invoices in one query
        $invoiceIds = array_column($invoices, 'id');
        $itemCounts = [];
        if (!empty($invoiceIds)) {
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $countStmt = $pdo->prepare("
                SELECT invoice_id, COUNT(*) as count 
                FROM invoice_items 
                WHERE invoice_id IN ($placeholders)
                GROUP BY invoice_id
            ");
            $countStmt->execute($invoiceIds);
            $countResults = $countStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($countResults as $row) {
                $itemCounts[$row['invoice_id']] = intval($row['count']);
            }
        }
        
        // Calculate actual remaining balance for each invoice using PaymentManager
        $mappedInvoices = array_map(function($invoice) use ($itemCounts, $paymentManager) {
            $invoiceId = $invoice['id'];
            $itemsCount = $itemCounts[$invoiceId] ?? 1;
            
            // Calculate actual values using PaymentManager
            $actualPaid = $paymentManager->calculateTotalPaid($invoiceId);
            $actualRemaining = $paymentManager->calculateRemainingBalance($invoiceId);
            $totalPrice = floatval($invoice['total_price'] ?? 0);
            
            // حساب حالة الدفع الفعلية بناءً على الأرقام المحسوبة
            $actualPaymentStatus = 'unpaid';
            if ($actualRemaining <= 0.01) {
                $actualPaymentStatus = 'paid';
            } elseif ($actualPaid > 0) {
                $actualPaymentStatus = 'partial';
            }
            
            return [
                'id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'] ?? 'N/A',
                'invoice_date' => $invoice['invoice_date'] ?? '',
                'customer_name' => $invoice['customer_name'] ?? 'غير محدد',
                'customer_phone' => $invoice['customer_phone'] ?? '',
                'customer_id' => $invoice['customer_id'] ?? null,
                'payment_status' => $actualPaymentStatus, // استخدام الحالة المحسوبة
                'invoice_status' => $invoice['invoice_status'] ?? 'reserved',
                'operation_type' => $invoice['operation_type'] ?? 'sale',
                'total_price' => $totalPrice,
                'deposit_amount' => $actualPaid, // استخدام المدفوع الفعلي
                'remaining_balance' => $actualRemaining,
                'wedding_date' => $invoice['wedding_date'],
                'collection_date' => $invoice['collection_date'],
                'return_date' => $invoice['return_date'],
                'delivered_at' => $invoice['delivered_at'],
                'returned_at' => $invoice['returned_at'],
                'items_count' => $itemsCount,
                // Backward compatibility
                'status' => $actualPaymentStatus,
                'paid' => $actualPaid,
                'remaining' => $actualRemaining
            ];
        }, $invoices);
        
        sendSuccess($mappedInvoices);
        
    } catch (Exception $e) {
        logError('Invoices List Error', ['message' => $e->getMessage()]);
        sendError('Failed to load invoices: ' . $e->getMessage(), 500);
    }
}

/**
 * تفاصيل فاتورة واحدة (محسّن)
 */
function handleDetails($pdo, $lifecycleManager, $paymentManager) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Invoice ID is required', 400);
            return;
        }
        
        // Get invoice with customer
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                c.name as customer_name,
                c.phone_1,
                c.phone_2,
                c.address as customer_address,
                c.type as customer_type,
                u.full_name as created_by_name,
                d.full_name as delivered_by_name,
                r.full_name as returned_by_name
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN users u ON i.created_by = u.id
            LEFT JOIN users d ON i.delivered_by = d.id
            LEFT JOIN users r ON i.returned_by = r.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            sendError('Invoice not found', 404);
            return;
        }
        
        // Get invoice items
        $stmt = $pdo->prepare("
            SELECT * FROM invoice_items 
            WHERE invoice_id = ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse measurements JSON
        foreach ($items as &$item) {
            if (!empty($item['measurements'])) {
                $item['measurements'] = json_decode($item['measurements'], true) ?: [];
            }
        }
        
        // Separate accessories and products
        $accessories = [];
        $products = [];
        $customDress = null;
        
        foreach ($items as $item) {
            if ($item['item_type'] === 'accessory') {
                $accessories[] = $item['item_name'];
            } elseif ($item['item_type'] === 'custom_dress') {
                if (!$customDress) {
                    $customDress = $item;
                    // Extract dress details
                    $measurements = $item['measurements'] ?? [];
                    
                    $customDress['dress_name'] = $item['item_name'] ?? '';
                    $customDress['dress_code'] = $item['item_code'] ?? '';
                    $customDress['dress_color'] = $item['color'] ?? '';
                    $customDress['dress_size'] = $item['size'] ?? '';
                    $customDress['bust_meas'] = $measurements['bust'] ?? '';
                    $customDress['waist_meas'] = $measurements['waist'] ?? '';
                    $customDress['dress_len'] = $measurements['length'] ?? '';
                    $customDress['sleeve_len'] = $measurements['sleeve'] ?? '';
                    $customDress['shoulder_len'] = $measurements['shoulder'] ?? '';
                    $customDress['other_meas'] = $measurements['other'] ?? '';
                }
                $products[] = $item;
            } else {
                $products[] = $item;
            }
        }
        
        // Get payments using PaymentManager
        $payments = $paymentManager->getInvoicePayments($id);
        $paymentSummary = $paymentManager->getPaymentSummary($id);
        
        // Get status history
        $history = $lifecycleManager->getInvoiceHistory($id);
        
        // Get Store Information for Print
        $storeInfo = ['name' => 'Store Name', 'slogan' => 'Luxury Wedding Dresses'];
        try {
            // Attempt to connect to master DB to get store details
            if (file_exists(__DIR__ . '/../../app/config/master_database.php') && isset($_SESSION['store_id'])) {
                $pdoMaster = require __DIR__ . '/../../app/config/master_database.php';
                $storeStmt = $pdoMaster->prepare("SELECT store_name as name, logo_path as logo FROM stores WHERE id = ?");
                $storeStmt->execute([$_SESSION['store_id']]);
                $fetchedStore = $storeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fetchedStore) {
                    $storeInfo['name'] = $fetchedStore['name'];
                    if (!empty($fetchedStore['logo'])) {
                        $storeInfo['logo'] = $fetchedStore['logo'];
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail for store info, log it
            error_log("Failed to fetch store info in invoice details: " . $e->getMessage());
        }
        
        // === FIX: Format all date fields properly ===
        // Convert NULL dates to empty strings and format valid dates
        $formatDate = function($date) {
            if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
                return null; // Return null for invalid/empty dates
            }
            return $date; // Return as-is, let frontend handle formatting
        };
        
        // Format invoice dates
        $invoice['wedding_date'] = $formatDate($invoice['wedding_date']);
        $invoice['collection_date'] = $formatDate($invoice['collection_date']);
        $invoice['return_date'] = $formatDate($invoice['return_date']);
        $invoice['delivered_at'] = $formatDate($invoice['delivered_at']);
        $invoice['returned_at'] = $formatDate($invoice['returned_at']);
        $invoice['invoice_date'] = $formatDate($invoice['invoice_date']);
        $invoice['created_at'] = $formatDate($invoice['created_at']);
        $invoice['updated_at'] = $formatDate($invoice['updated_at']);
        
        // Ensure all text fields have defaults
        $invoice['payment_method'] = $invoice['payment_method'] ?? '';
        $invoice['operation_type'] = $invoice['operation_type'] ?? 'sale';
        $invoice['return_condition'] = $invoice['return_condition'] ?? null;
        $invoice['notes'] = $invoice['notes'] ?? '';
        
        // Format response
        $response = [
            'invoice' => $invoice,
            'item' => $customDress ?: (count($products) > 0 ? $products[0] : []),
            'items' => $products,
            'accessories' => $accessories,
            'payments' => $payments,
            'payment_summary' => $paymentSummary,
            'status_history' => $history,
            'store_info' => $storeInfo
        ];
        
        sendSuccess($response);
        
    } catch (Exception $e) {
        logError('Get Invoice Error', ['message' => $e->getMessage()]);
        sendError('Failed to load invoice: ' . $e->getMessage(), 500);
    }
}

/**
 * إحصائيات الفواتير
 */
function handleStats($pdo) {
    try {
        $filter = $_GET['period'] ?? ($_GET['date'] ?? 'all');
        
        // Load RevenueCalculator for cash-based revenue tracking
        require_once __DIR__ . '/../../app/helpers/revenue_calculator.php';
        $revenueCalculator = new RevenueCalculator($pdo);
        
        // Get total sales and count from invoices
        $sql = "SELECT 
            COALESCE(SUM(total_price), 0) as total_sales,
            COALESCE(SUM(remaining_balance), 0) as total_remaining,
            COUNT(*) as total_invoices
        FROM invoices
        WHERE deleted_at IS NULL";
        
        $params = [];
        $startDate = null;
        $endDate = date('Y-m-d'); // Today
        
        if ($filter === 'daily' || $filter === 'today') {
            // STRICT: Only invoices dated exactly TODAY (excludes future dates)
            $sql .= " AND DATE(invoice_date) = CURDATE()";
            $startDate = $endDate;
        } elseif ($filter === 'weekly' || $filter === 'week') {
            // STRICT: Only invoices within THIS week AND not in the future
            // Mode 0 = Sunday-start weeks (aligned with Saudi Arabia/Middle East business conventions)
            $sql .= " AND YEARWEEK(invoice_date, 0) = YEARWEEK(CURDATE(), 0) AND DATE(invoice_date) <= CURDATE()";
            // Calculate start of week
            $startDate = date('Y-m-d', strtotime('sunday this week'));
        } elseif ($filter === 'month' || $filter === 'monthly') {
            // STRICT: Only invoices within THIS month AND not in the future
            $sql .= " AND MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) AND DATE(invoice_date) <= CURDATE()";
            // Calculate start of month
            $startDate = date('Y-m-01');
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalInvoices = intval($stats['total_invoices'] ?? 0);
        $totalSales = floatval($stats['total_sales'] ?? 0);
        $avgInvoice = $totalInvoices > 0 ? ($totalSales / $totalInvoices) : 0;
        
        // ✅ CRITICAL FIX: Calculate revenue using CASH-BASED calculation
        // This includes BOTH initial deposits AND all subsequent payments
        $totalRevenue = 0;
        if ($startDate !== null && $endDate !== null) {
            if ($startDate === $endDate) {
                // Single day
                $totalRevenue = $revenueCalculator->calculateDailyRevenue($startDate);
            } else {
                // Date range
                $totalRevenue = $revenueCalculator->calculateRevenueForPeriod($startDate, $endDate);
            }
        } else {
            // All time - need to calculate differently
            // For 'all' period, we need total of all deposits + all payments
            try {
                $allDepositsStmt = $pdo->query("
                    SELECT COALESCE(SUM(deposit_amount), 0) as total_deposits
                    FROM invoices 
                    WHERE deleted_at IS NULL
                ");
                $allDepositsResult = $allDepositsStmt->fetch(PDO::FETCH_ASSOC);
                $allDeposits = floatval($allDepositsResult['total_deposits'] ?? 0);
                
                $allPaymentsStmt = $pdo->query("
                    SELECT COALESCE(SUM(amount), 0) as total_payments
                    FROM payments 
                    WHERE type = 'payment'
                    AND (notes NOT LIKE '%تم نقله تلقائياً%' OR notes IS NULL)
                ");
                $allPaymentsResult = $allPaymentsStmt->fetch(PDO::FETCH_ASSOC);
                $allPayments = floatval($allPaymentsResult['total_payments'] ?? 0);
                
                $totalRevenue = $allDeposits + $allPayments;
            } catch (Exception $e) {
                error_log("Invoice Stats: Error calculating all-time revenue: " . $e->getMessage());
                $totalRevenue = floatval($stats['total_sales'] ?? 0); // Fallback
            }
        }
        
        $mappedStats = [
            'total_sales' => $totalSales,
            'today_total' => $totalSales,
            'total_revenue' => $totalRevenue, // ✅ NOW CASH-BASED
            'today_revenue' => $totalRevenue,  // ✅ NOW CASH-BASED
            'total_remaining' => floatval($stats['total_remaining'] ?? 0),
            'total_invoices' => $totalInvoices,
            'today_count' => $totalInvoices,
            'today_avg' => $avgInvoice,
            'average_invoice' => $avgInvoice, // Frontend compatibility field
            'period' => $filter
        ];
        
        sendSuccess($mappedStats);
        
    } catch (Exception $e) {
        logError('Invoice Stats Error', ['message' => $e->getMessage()]);
        sendError('Failed to load statistics: ' . $e->getMessage(), 500);
    }
}

/**
 * توليد رقم فاتورة جديد
 */
function handleGenerateNumber($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT invoice_number 
            FROM invoices 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = 1;
        
        if ($last && preg_match('/INV-(\d+)/', $last['invoice_number'], $matches)) {
            $nextNum = intval($matches[1]) + 1;
        }
        
        $nextNumber = 'INV-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // Check uniqueness
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE invoice_number = ?");
        $checkStmt->execute([$nextNumber]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        $maxAttempts = 100;
        $attempts = 0;
        while ($exists && $exists['count'] > 0 && $attempts < $maxAttempts) {
            $nextNum++;
            $nextNumber = 'INV-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            $checkStmt->execute([$nextNumber]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $attempts++;
        }
        
        sendSuccess(['invoice_number' => $nextNumber]);
        
    } catch (Exception $e) {
        logError('Generate Number Error', ['message' => $e->getMessage()]);
        sendError('Failed to generate invoice number: ' . $e->getMessage(), 500);
    }
}

/**
 * تغيير حالة الفاتورة
 */
function handleChangeStatus($pdo, $lifecycleManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // Permission check
    try {
        $permissions->requirePermission('edit_invoice');
    } catch (Exception $e) {
        sendError($e->getMessage(), 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $newStatus = $input['new_status'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (!$invoiceId || !$newStatus) {
        sendError('معرف الفاتورة والحالة الجديدة مطلوبان', 400);
        return;
    }
    
    try {
        $result = $lifecycleManager->changeStatus($invoiceId, $newStatus, $userId, $notes);
        sendSuccess($result);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * تأكيد تسليم الفستان
 */
function handleDeliver($pdo, $lifecycleManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (!$invoiceId) {
        sendError('معرف الفاتورة مطلوب', 400);
        return;
    }
    
    try {
        $result = $lifecycleManager->deliverInvoice($invoiceId, $userId, $notes);
        sendSuccess($result);
        
    } catch (Exception $e) {
        // أخطاء المنطق (مثل حالة غير مسموحة) ترجع 400
        // أخطاء النظام (قاعدة البيانات، إلخ) ترجع 500
        $isBusinessError = (
            strpos($e->getMessage(), 'لا يمكن') !== false ||
            strpos($e->getMessage(), 'غير موجودة') !== false ||
            strpos($e->getMessage(), 'يجب') !== false
        );
        $code = $isBusinessError ? 400 : 500;
        logError('handleDeliver error', [
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'message' => $e->getMessage(),
            'code' => $code
        ]);
        sendError($e->getMessage(), $code);
    }
}

/**
 * تأكيد إرجاع الفستان
 */
function handleReturn($pdo, $lifecycleManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $condition = $input['condition'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (!$invoiceId || !$condition) {
        sendError('معرف الفاتورة وحالة الإرجاع مطلوبان', 400);
        return;
    }
    
    try {
        $result = $lifecycleManager->returnInvoice($invoiceId, $userId, $condition, $notes);
        sendSuccess($result);
        
    } catch (Exception $e) {
        $isBusinessError = (
            strpos($e->getMessage(), 'لا يمكن') !== false ||
            strpos($e->getMessage(), 'غير موجودة') !== false ||
            strpos($e->getMessage(), 'يجب') !== false
        );
        $code = $isBusinessError ? 400 : 500;
        logError('handleReturn error', [
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'condition' => $condition,
            'message' => $e->getMessage(),
            'code' => $code
        ]);
        sendError($e->getMessage(), $code);
    }
}

/**
 * إقفال الفاتورة
 */
function handleClose($pdo, $lifecycleManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // Permission check (Manager or above)
    if (!$permissions->canCloseInvoice()) {
        sendError('ليس لديك صلاحية إقفال الفواتير', 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (!$invoiceId) {
        sendError('معرف الفاتورة مطلوب', 400);
        return;
    }
    
    try {
        $result = $lifecycleManager->closeInvoice($invoiceId, $userId, $notes);
        sendSuccess($result);
        
    } catch (Exception $e) {
        $isBusinessError = (
            strpos($e->getMessage(), 'لا يمكن') !== false ||
            strpos($e->getMessage(), 'غير موجودة') !== false ||
            strpos($e->getMessage(), 'يجب') !== false ||
            strpos($e->getMessage(), 'قبل') !== false
        );
        $code = $isBusinessError ? 400 : 500;
        logError('handleClose error', [
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'message' => $e->getMessage(),
            'code' => $code
        ]);
        sendError($e->getMessage(), $code);
    }
}

/**
 * إلغاء الفاتورة (Soft Delete)
 */
function handleCancel($pdo, $lifecycleManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // Permission check (Manager or above)
    if (!$permissions->canCancelInvoice()) {
        sendError('ليس لديك صلاحية إلغاء الفواتير', 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['id'] ?? $input['invoice_id'] ?? null;
    $reason = $input['reason'] ?? 'لم يتم تحديد السبب';
    
    if (!$invoiceId) {
        sendError('معرف الفاتورة مطلوب', 400);
        return;
    }
    
    try {
        $result = $lifecycleManager->cancelInvoice($invoiceId, $userId, $reason);
        sendSuccess($result);
        
    } catch (Exception $e) {
        $isBusinessError = (
            strpos($e->getMessage(), 'لا يمكن') !== false ||
            strpos($e->getMessage(), 'غير موجودة') !== false
        );
        $code = $isBusinessError ? 400 : 500;
        logError('handleCancel error', [
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'reason' => $reason,
            'message' => $e->getMessage(),
            'code' => $code
        ]);
        sendError($e->getMessage(), $code);
    }
}

/**
 * قائمة الأرشيف (الفواتير المقفلة/الملغاة/المرتجعة)
 */
function handleArchiveList($pdo, $paymentManager) {
    try {
        $statusFilter = $_GET['status'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $sql = "
            SELECT 
                i.id,
                i.invoice_number,
                i.invoice_date,
                i.payment_status,
                i.invoice_status,
                i.operation_type,
                i.total_price,
                i.delivered_at,
                i.returned_at,
                i.deleted_at,
                c.name as customer_name,
                c.id as customer_id
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.invoice_status IN ('closed', 'canceled', 'returned')
               OR i.deleted_at IS NOT NULL
        ";
        
        $params = [];
        
        if (!empty($statusFilter)) {
            $sql .= " AND i.invoice_status = ?";
            $params[] = $statusFilter;
        }
        
        if (!empty($startDate)) {
            $sql .= " AND DATE(i.invoice_date) >= ?";
            $params[] = $startDate;
        }
        
        if (!empty($endDate)) {
            $sql .= " AND DATE(i.invoice_date) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY i.updated_at DESC, i.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccess([
            'invoices' => $invoices,
            'count' => count($invoices)
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * إرسال رسالة WhatsApp مع الفاتورة
 */
function handleSendInvoiceMessage($pdo) {
    try {
        // Load WhatsApp helper
        $whatsappHelperPath = __DIR__ . '/../../app/helpers/whatsapp.php';
        if (!file_exists($whatsappHelperPath)) {
            sendError('WhatsApp helper not found', 500);
            return;
        }
        require_once $whatsappHelperPath;
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Invoice ID is required', 400);
            return;
        }
        
        // Get invoice with customer data
        $stmt = $pdo->prepare("
            SELECT 
                i.invoice_number,
                c.name as customer_name,
                c.phone_1
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            sendError('Invoice not found', 404);
            return;
        }
        
        if (empty($invoice['phone_1'])) {
            sendError('Customer phone number not found', 400);
            return;
        }
        
        // Format and send WhatsApp message with PDF
        $customerName = $invoice['customer_name'] ?? 'عزيزنا العميل';
        $invoiceNumber = $invoice['invoice_number'];
        $message = formatInvoiceMessage($customerName, $invoiceNumber, 'reminder');
        
        // Try to send with PDF first
        $result = null;
        if (function_exists('generateInvoicePDFUrl') && function_exists('sendWhatsAppWithFile')) {
            $pdfUrl = generateInvoicePDFUrl($id, $pdo);
            
            if ($pdfUrl) {
                $result = sendWhatsAppWithFile(
                    $invoice['phone_1'],
                    $message,
                    $pdfUrl,
                    'invoice_' . $invoiceNumber . '.html'
                );
            }
        }
        
        // Fallback to text-only if PDF sending failed
        if (!$result || !$result['success']) {
            $result = sendWhatsApp($invoice['phone_1'], $message);
        }
        
        if ($result['success']) {
            sendSuccess([
                'message' => 'تم إرسال رسالة WhatsApp بنجاح',
                'whatsapp_result' => $result
            ]);
        } else {
            sendError('فشل إرسال رسالة WhatsApp: ' . ($result['error'] ?? 'Unknown error'), 500);
        }
        
    } catch (Exception $e) {
        logError('Send Invoice Message Error', ['message' => $e->getMessage()]);
        sendError('Failed to send WhatsApp message: ' . $e->getMessage(), 500);
    }
}

/**
 * Check product availability (للتحقق من الحجز المزدوج)
 */
function handleCheckAvailability($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // Load double booking checker
    require_once __DIR__ . '/../../app/helpers/double_booking_checker.php';
    
    $input = getJsonInput();
    
    $productId = $input['product_id'] ?? null;
    $collectionDate = $input['collection_date'] ?? null;
    $returnDate = $input['return_date'] ?? null;
    $currentInvoiceId = $input['current_invoice_id'] ?? null;
    
    if (!$productId || !$collectionDate || !$returnDate) {
        sendError('البيانات المطلوبة غير مكتملة', 400);
        return;
    }
    
    try {
        $checker = new DoubleBookingChecker($pdo);
        
        $result = $checker->checkAvailability(
            $productId,
            $collectionDate,
            $returnDate,
            $currentInvoiceId
        );
        
        sendSuccess($result);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}
