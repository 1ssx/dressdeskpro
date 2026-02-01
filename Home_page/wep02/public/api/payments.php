<?php
/**
 * Payments API
 * 
 * إدارة المدفوعات المتعددة للفواتير
 * 
 * Actions:
 * - list: جلب قائمة المدفوعات لفاتورة
 * - summary: ملخص المدفوعات
 * - add: إضافة دفعة جديدة
 * - delete: حذف دفعة (admin فقط)
 * - add_refund: إضافة مرتجع
 * - add_penalty: إضافة غرامة
 */

// Load common infrastructure
require_once __DIR__ . '/_common/bootstrap.php';

// Load required helpers
require_once __DIR__ . '/../../app/helpers/payment_manager.php';
require_once __DIR__ . '/../../app/helpers/permissions.php';
require_once __DIR__ . '/../../app/helpers/logger.php';

$action = $_GET['action'] ?? 'list';

try {
    $paymentManager = new PaymentManager($pdo);
    $permissions = new PermissionsManager($pdo, $_SESSION['user_id'] ?? null);
    $logger = new StoreLogger($pdo);
    $userId = $_SESSION['user_id'] ?? null;
    
    switch ($action) {
        case 'list':
            handleList($pdo, $paymentManager);
            break;
            
        case 'summary':
            handleSummary($pdo, $paymentManager);
            break;
            
        case 'add':
            handleAdd($pdo, $paymentManager, $permissions, $logger, $userId);
            break;
            
        case 'delete':
            handleDelete($pdo, $paymentManager, $permissions, $logger, $userId);
            break;
            
        case 'add_refund':
            handleAddRefund($pdo, $paymentManager, $permissions, $logger, $userId);
            break;
            
        case 'add_penalty':
            handleAddPenalty($pdo, $paymentManager, $permissions, $logger, $userId);
            break;
            
        default:
            sendError('Invalid action', 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * جلب قائمة المدفوعات لفاتورة معينة
 */
function handleList($pdo, $paymentManager) {
    $invoiceId = $_GET['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        sendError('معرف الفاتورة مطلوب', 400);
        return;
    }
    
    try {
        $payments = $paymentManager->getInvoicePayments($invoiceId);
        
        sendSuccess([
            'payments' => $payments,
            'count' => count($payments)
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * جلب ملخص المدفوعات لفاتورة معينة
 */
function handleSummary($pdo, $paymentManager) {
    $invoiceId = $_GET['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        sendError('معرف الفاتورة مطلوب', 400);
        return;
    }
    
    try {
        $summary = $paymentManager->getPaymentSummary($invoiceId);
        
        if (!$summary) {
            sendError('الفاتورة غير موجودة', 404);
            return;
        }
        
        sendSuccess($summary);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * إضافة دفعة جديدة
 */
function handleAdd($pdo, $paymentManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // التحقق من الصلاحيات
    try {
        $permissions->requirePermission('add_payment');
    } catch (Exception $e) {
        sendError($e->getMessage(), 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $requiredFields = ['invoice_id', 'amount', 'method'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            sendError("الحقل {$field} مطلوب", 400);
            return;
        }
    }
    
    $invoiceId = intval($input['invoice_id']);
    $amount = floatval($input['amount']);
    $method = $input['method'];
    $type = $input['type'] ?? PaymentManager::TYPE_PAYMENT;
    $notes = $input['notes'] ?? null;
    $paymentDate = $input['payment_date'] ?? null;
    
    // Validate amount
    if ($amount <= 0) {
        sendError('المبلغ يجب أن يكون أكبر من صفر', 400);
        return;
    }
    
    // Validate method
    $validMethods = ['cash', 'card', 'transfer', 'mixed'];
    if (!in_array($method, $validMethods)) {
        sendError('طريقة الدفع غير صحيحة', 400);
        return;
    }
    
    // Validate type
    $validTypes = [PaymentManager::TYPE_PAYMENT, PaymentManager::TYPE_REFUND, PaymentManager::TYPE_PENALTY];
    if (!in_array($type, $validTypes)) {
        sendError('نوع الدفعة غير صحيح', 400);
        return;
    }
    
    try {
        $result = $paymentManager->addPayment(
            $invoiceId,
            $amount,
            $method,
            $type,
            $notes,
            $userId,
            $paymentDate
        );
        
        sendSuccess($result);
        
    } catch (Exception $e) {
        // أخطاء المنطق (مثل المبلغ أكبر من المتبقي) ترجع 400
        $isBusinessError = (
            strpos($e->getMessage(), 'لا يمكن') !== false ||
            strpos($e->getMessage(), 'أكبر من') !== false ||
            strpos($e->getMessage(), 'غير موجودة') !== false ||
            strpos($e->getMessage(), 'يجب') !== false ||
            strpos($e->getMessage(), 'ملغاة') !== false
        );
        $code = $isBusinessError ? 400 : 500;
        sendError($e->getMessage(), $code);
    }
}

/**
 * حذف دفعة
 */
function handleDelete($pdo, $paymentManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // التحقق من الصلاحيات (admin فقط)
    if (!$permissions->canDeletePayment()) {
        sendError('ليس لديك صلاحية حذف الدفعات', 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $paymentId = $input['payment_id'] ?? null;
    $reason = $input['reason'] ?? 'لم يتم تحديد السبب';
    
    if (!$paymentId) {
        sendError('معرف الدفعة مطلوب', 400);
        return;
    }
    
    try {
        $result = $paymentManager->deletePayment($paymentId, $userId, $reason);
        
        sendSuccess($result);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * إضافة مرتجع
 */
function handleAddRefund($pdo, $paymentManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // التحقق من الصلاحيات
    try {
        $permissions->requirePermission('add_payment');
    } catch (Exception $e) {
        sendError($e->getMessage(), 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $amount = floatval($input['amount'] ?? 0);
    $reason = $input['reason'] ?? '';
    
    if (!$invoiceId || $amount <= 0) {
        sendError('معرف الفاتورة والمبلغ مطلوبان', 400);
        return;
    }
    
    try {
        $result = $paymentManager->addRefund($invoiceId, $amount, $reason, $userId);
        
        sendSuccess($result);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

/**
 * إضافة غرامة
 */
function handleAddPenalty($pdo, $paymentManager, $permissions, $logger, $userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
        return;
    }
    
    // التحقق من الصلاحيات (manager أو أعلى)
    if (!$permissions->isManagerOrAbove()) {
        sendError('ليس لديك صلاحية إضافة غرامات', 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $invoiceId = $input['invoice_id'] ?? null;
    $amount = floatval($input['amount'] ?? 0);
    $reason = $input['reason'] ?? '';
    
    if (!$invoiceId || $amount <= 0 || empty($reason)) {
        sendError('معرف الفاتورة والمبلغ والسبب مطلوبان', 400);
        return;
    }
    
    try {
        $result = $paymentManager->addPenalty($invoiceId, $amount, $reason, $userId);
        
        sendSuccess($result);
        
    } catch (Exception $e) {
        sendError($e->getMessage(), 500);
    }
}
